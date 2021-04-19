<?php

namespace OCA\DelayedPreview\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;

class PreviewMiddleware extends Middleware {

    public function afterController($controller, $methodName, $response){
        if ($response->getStatus() != Http::STATUS_NOT_FOUND) {
            return $response;
        }

        if ($controller instanceof \OC\Core\Controller\PreviewController) {
            $root = \OC::$server->getRootFolder();
            $userId = \OC::$server->getUserSession()->getUser()->getUID();
            $request = \OC::$server->getRequest();
            $config = \OC::$server->getConfig();
            $userFolder = $root->getUserFolder($userId);

            if ($methodName === 'getPreview') {
                try {
                    $file = $userFolder->get($request->getParam('file'));
                } catch (NotFoundException $e) {
                    return new DataResponse([], Http::STATUS_NOT_FOUND);
                }
            } elseif ($methodName === 'getPreviewByFileId') {
                $nodes = $userFolder->getById($request->getParam('fileId'));
                if (\count($nodes) === 0) {
                    return new DataResponse([], Http::STATUS_NOT_FOUND);
                }
                $file = array_pop($nodes);
            }

            $mimeType = $file->getMimeType();

            if ($mimeType == 'image/heic' && strpos($_SERVER['HTTP_USER_AGENT'], 'Nextcloud') !== false) {
                // heic for browser
            } else {
                if ($file->getSize() < 2000000
                    || $request->getParam('x') > 1024
                    || $request->getParam('y') > 1024
                ) {
                    switch ($mimeType) {
                        case 'image/png':
                        case 'image/jpeg':
                        case 'image/gif':
                        case 'image/heic':
                            $response = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => $file->getMimeType()]);
                            $response->cacheFor(3600 * 24 * 24);
                            return $response;
                            break;
                        default:
                            // skip
                    }
                }
            }

            if ($request->getParam('a') === 'false') {
                $crop = true;
            } else {
                $crop = ! $request->getParam('a');
            }

            $this->pushToQueue(
                $userId,
                $file->getId(),
                $request->getParam('x'),
                $request->getParam('y'),
                $crop,
                $request->getParam('mode') ?: 'fill'
            );

        }

        return $response;
    }

    protected function pushToQueue(
        $uid,
        $id,
        $width,
        $height,
        $crop,
        $mode
    ) {
        $redis = \OC::$server->getGetRedisFactory()->getInstance();
        $key = sprintf('dp:%s:%d:%d:%d:%s', $id, $width, $height, $crop, $mode);
        if ($redis->setnx($key, 1)) {
            $redis->expire($key, 300);
            $redis->rPush('delayed_previews_queue', json_encode([
                'uid' => $uid,
                'id' => $id,
                'w' => $width,
                'h' => $height,
                'crop' => $crop,
                'mode' => $mode,
            ], JSON_UNESCAPED_SLASHES));
        }
    }

}
