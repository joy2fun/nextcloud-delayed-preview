<?php

namespace OCA\DelayedPreview;

use OC\PreviewManager;
use OCP\IPreview;
use OCP\Files\File;


class DelayedPreviewManager extends PreviewManager {

    private $delayedGenerator;

    public function getPreview(File $file, $width = -1, $height = -1, $crop = false, $mode = IPreview::MODE_FILL, $mimeType = null) {
        return $this->getDelayedGenerator()->getPreview($file, $width, $height, $crop, $mode, $mimeType);
    }

    public function generatePreviews(File $file, array $specifications, $mimeType = null) {
        return $this->getDelayedGenerator()->generatePreviews($file, $specifications, $mimeType);
    }

    private function getDelayedGenerator() {
        if ($this->delayedGenerator === null) {
            $this->delayedGenerator = new DelayedGenerator(
                $this->config,
                $this,
                $this->appData,
                new \OC\Preview\GeneratorHelper(
                    $this->rootFolder,
                    $this->config
                ),
                $this->eventDispatcher
            );
        }
        return $this->delayedGenerator;
    }
}