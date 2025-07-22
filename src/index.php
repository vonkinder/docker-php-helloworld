<?php

class SimpleS3Client {
    private $accessKey;
    private $secretKey;
    private $region;
    private $bucket;
    
    public function __construct($accessKey, $secretKey, $region, $bucket) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->bucket = $bucket;
    }
    
    public function downloadFile($s3Key, $localPath = null) {
        // If no local path specified, use the S3 key as filename
        if (!$localPath) {
            $localPath = basename($s3Key);
        }
        
        $host = "s3.{$this->region}.amazonaws.com";
        $url = "https://{$host}/{$this->bucket}/{$s3Key}";
        
        // Create timestamp
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        // Create canonical request
        $canonicalRequest = $this->createCanonicalRequest('GET', "/{$this->bucket}/{$s3Key}", '', $host, $timestamp);
        
        // Create string to sign
        $stringToSign = $this->createStringToSign($timestamp, $date, $canonicalRequest);
        
        // Calculate signature
        $signature = $this->calculateSignature($stringToSign, $date);
        
        // Create authorization header
        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$date}/{$this->region}/s3/aws4_request, SignedHeaders=host;x-amz-date, Signature={$signature}";
        
        // Set up cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                "Host: {$host}",
                "X-Amz-Date: {$timestamp}",
                "Authorization: {$authHeader}"
            ],
            CURLOPT_FILE => fopen($localPath, 'w')
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($result === false || $httpCode !== 200) {
            echo "Error downloading file. HTTP Code: {$httpCode}\n";
            return false;
        }
        
        echo "File downloaded successfully to: {$localPath}\n";
        return true;
    }
    
    private function createCanonicalRequest($method, $uri, $queryString, $host, $timestamp) {
        $canonicalHeaders = "host:{$host}\nx-amz-date:{$timestamp}\n";
        $signedHeaders = "host;x-amz-date";
        $payloadHash = hash('sha256', '');
        
        return "{$method}\n{$uri}\n{$queryString}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
    }
    
    private function createStringToSign($timestamp, $date, $canonicalRequest) {
        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "{$date}/{$this->region}/s3/aws4_request";
        $canonicalRequestHash = hash('sha256', $canonicalRequest);
        
        return "{$algorithm}\n{$timestamp}\n{$credentialScope}\n{$canonicalRequestHash}";
    }
    
    private function calculateSignature($stringToSign, $date) {
        $kDate = hash_hmac('sha256', $date, "AWS4{$this->secretKey}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        
        return hash_hmac('sha256', $stringToSign, $kSigning);
    }
}

// Usage example:
$accessKey = 'YOURKEYS';
$secretKey = 'YOURSECRETKEYS';
$region = 'us-east-1';  // Change to your bucket's region
$bucket = 'ace-backup-june';

$s3Client = new SimpleS3Client($accessKey, $secretKey, $region, $bucket);

// Download a file
$s3Key = 'Untitledpresentation.pptx.pptx';  // The key/path of the file in S3
$localPath = 'downloaded_file.pptx';  // Where to save it locally

$s3Client->downloadFile($s3Key, $localPath);

?>