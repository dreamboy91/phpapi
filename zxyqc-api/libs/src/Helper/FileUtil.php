<?php

namespace Helper;

use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeExtensionGuesser;

class FileUtil
{
    /**
     * @param $dir
     * @param $baseFileName
     * @param $files
     * @return array
     */
    public static function uploadFileFromDataUrls($dir, $baseFileName, $files,$index)
    {
        $photos = array();

        foreach ($files as $key => $file) {
            $imageDataArray = self::getBinaryDataFromDataUrl($file);

            if (empty($imageDataArray)) {
                break;
            }

            $photos[$key] = "{$baseFileName}{$index}." . $imageDataArray['type'];
            file_put_contents($dir . DIRECTORY_SEPARATOR . $photos[$key], $imageDataArray['data']);
            $index = $index+1;
            }

        return $photos;
    }

    /**
     * @param $image
     * @return string
     */
    protected static function getBinaryDataFromDataUrl($image)
    {
        if (!preg_match('/data:([^;]*);base64,(.*)/', $image, $matches)) {
            return array();
        }

        return array(
            'type' => self::getExtensionGuesser()->guess($matches[1]),
            'data' => base64_decode($matches[2])
        );
    }

    /**
     * @return MimeTypeExtensionGuesser
     */
    protected static function getExtensionGuesser()
    {
        return new MimeTypeExtensionGuesser();
    }
}