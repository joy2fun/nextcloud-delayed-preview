<?php

namespace OCA\DelayedPreview\Command;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Encryption\IManager;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IUserManager;
use OCP\Files\IAppData;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Generate extends Command {

    /** @var string */
    protected $appName;

    /** @var IAppData */
    private $appData;

    /** @var IUserManager */
    protected $userManager;

    /** @var IRootFolder */
    protected $rootFolder;

    /** @var IPreview */
    protected $previewGenerator;

    /** @var IConfig */
    protected $config;

    /** @var OutputInterface */
    protected $output;

    /** @var int[][] */
    protected $sizes;

    /** @var IManager */
    protected $encryptionManager;

    protected $processedCount = 0;

    /**
     * @param string $appName
     * @param IPreview $previewGenerator
     * @param IConfig $config
     * @param IAppData $appData
     * @param IManager $encryptionManager
     */
    public function __construct($appName,
                                IRootFolder $rootFolder,
                                IUserManager $userManager,
                                IPreview $previewGenerator,
                                IConfig $config,
                                IAppData $appData,
                                IManager $encryptionManager) {
        parent::__construct();

        $this->appName = $appName;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->previewGenerator = $previewGenerator;
        $this->config = $config;
        $this->appData = $appData;
        $this->encryptionManager = $encryptionManager;
    }

    protected function configure() {
        $this
            ->setName('preview:generate-delayed')
            ->setDescription('Generate delayed previews');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        if ($this->encryptionManager->isEnabled()) {
            $output->writeln('Encryption is enabled. Aborted.');
            return 1;
        }

        if ($this->checkAlreadyRunning()) {
            $output->writeln('Command is already running.');
            return 2;
        }

        $this->output = $output;

        $this->setPID();

        $this->startProcessing();

        $this->clearPID();

        return 0;
    }

    private function startProcessing() {
        $redis = \OC::$server->getGetRedisFactory()->getInstance();
        while($element = $redis->lPop('delayed_previews_queue')) {
            if (++ $this->processedCount > 2000) {
                break;
            }
            $item = json_decode($element);
            if (! isset($item->uid)) {
                $this->output->writeln('bad element: ' . $element);
                continue;
            }
            $folder = $this->rootFolder->getUserFolder($item->uid);
            $nodes = $folder->getById($item->id);
            if ($file = array_pop($nodes)) {
                $this->output->writeln('#'.$item->id . ' ' . $element);
                try {
                    $this->previewGenerator->getPreview(
                        $file,
                        $item->w,
                        $item->h,
                        $item->crop
                    );
                } catch (NotFoundException $e) {
                    $this->output->writeln('File not found: '. $file->getPath());
                } catch (\Exception $e) {
                    \OC::$server->getLogger()->error('generate delayed preview error: ', $e->getMessage());
                }

            } else {
                $this->output->writeln('invalid #'.$item->id);
            }
        }
    }

    private function setPID() {
        $this->config->setAppValue($this->appName, 'pid', posix_getpid());
    }

    private function clearPID() {
        $this->config->deleteAppValue($this->appName, 'pid');
    }

    private function getPID() {
        return (int)$this->config->getAppValue($this->appName, 'pid', -1);
    }

    private function checkAlreadyRunning() {
        $pid = $this->getPID();

        // No PID set so just continue
        if ($pid === -1) {
            return false;
        }

        // Get get the gid of non running processes so continue
        if (posix_getpgid($pid) === false) {
            return false;
        }

        // Seems there is already a running process generating previews
        return true;
    }
}
