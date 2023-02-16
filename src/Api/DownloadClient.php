<?php

declare(strict_types=1);

namespace OpenFoodFacts\Api;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use OpenFoodFacts\Exception\BadRequestException;

trait DownloadClient
{
    abstract protected function getHttpClient(): ClientInterface;
    abstract protected function getHost(): string;
    abstract protected function getCurrentApi(): string;

    private string $baseUrl;

    /**
     * This constant defines the extensions authorized for the downloading of the data
     * @var array<string, string>
     */
    private array $fileTypeMap = [
        'mongodb'   => 'openfoodfacts-mongodbdump.tar.gz',
        'csv'       => 'en.openfoodfacts.org.products.csv',
        'rdf'       => 'en.openfoodfacts.org.products.rdf'
    ];

    /**
     * @throws BadRequestException
     */
    protected function download(string $filePath, string $fileType = "mongodb"): bool
    {
        if (!isset($this->fileTypeMap[$fileType])) {
            throw new BadRequestException(sprintf('File type not recognized (available format : %s)', implode(',', array_keys($this->fileTypeMap))));
        }
        $url = sprintf('%s/data/%s', $this->getHost(), $this->fileTypeMap[$fileType]);
        try {
            $response = $this->getHttpClient()->request('GET', $url, ['sink' => $filePath]);
        } catch (GuzzleException $guzzleException) {
            throw new BadRequestException('Download failed : ' . $guzzleException->getMessage(), $guzzleException->getCode(), $guzzleException);
        }

        if ($response->getStatusCode() !== 200) {
            throw new BadRequestException(sprintf('Download failed - status code : %s', $response->getStatusCode()));
        }

        return true;
    }
}
