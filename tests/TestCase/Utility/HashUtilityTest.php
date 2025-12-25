<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Utility;

use AttributeRegistry\Utility\HashUtility;
use PHPUnit\Framework\TestCase;

class HashUtilityTest extends TestCase
{
    public function testHashReturnsConsistentValue(): void
    {
        $value = 'test_string';
        $hash1 = HashUtility::hash($value);
        $hash2 = HashUtility::hash($value);

        $this->assertSame($hash1, $hash2, 'Hash should be consistent for same input');
    }

    public function testHashReturnsDifferentValueForDifferentInput(): void
    {
        $hash1 = HashUtility::hash('string1');
        $hash2 = HashUtility::hash('string2');

        $this->assertNotSame($hash1, $hash2, 'Hash should differ for different inputs');
    }

    public function testHashReturnsString(): void
    {
        $hash = HashUtility::hash('test');

        $this->assertNotEmpty($hash);
        $this->assertSame(16, strlen($hash), 'xxh3 hash should be 16 characters');
    }

    public function testHashEmptyString(): void
    {
        $hash = HashUtility::hash('');

        $this->assertNotEmpty($hash, 'Empty string should still produce a hash');
        $this->assertSame(16, strlen($hash));
    }

    public function testHashFileReturnsConsistentValue(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'hash_test_');
        file_put_contents($tempFile, 'test content');

        $hash1 = HashUtility::hashFile($tempFile);
        $hash2 = HashUtility::hashFile($tempFile);

        $this->assertSame($hash1, $hash2, 'File hash should be consistent');

        unlink($tempFile);
    }

    public function testHashFileReturnsDifferentValueForDifferentContent(): void
    {
        $tempFile1 = tempnam(sys_get_temp_dir(), 'hash_test_');
        $tempFile2 = tempnam(sys_get_temp_dir(), 'hash_test_');

        file_put_contents($tempFile1, 'content1');
        file_put_contents($tempFile2, 'content2');

        $hash1 = HashUtility::hashFile($tempFile1);
        $hash2 = HashUtility::hashFile($tempFile2);

        $this->assertNotSame($hash1, $hash2, 'Different file contents should produce different hashes');

        unlink($tempFile1);
        unlink($tempFile2);
    }

    public function testHashFileReturnsFalseForNonExistentFile(): void
    {
        $nonExistentFile = '/tmp/this_file_does_not_exist_' . uniqid() . '.php';

        $result = HashUtility::hashFile($nonExistentFile);

        $this->assertFalse($result, 'Hash of non-existent file should return false');
    }

    public function testHashFileReturnsString(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'hash_test_');
        file_put_contents($tempFile, 'test content');

        $hash = HashUtility::hashFile($tempFile);

        $this->assertNotFalse($hash);
        $this->assertNotEmpty($hash);
        $this->assertSame(16, strlen($hash), 'xxh3 file hash should be 16 characters');

        unlink($tempFile);
    }

    public function testHashFileEmptyFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'hash_test_');
        file_put_contents($tempFile, '');

        $hash = HashUtility::hashFile($tempFile);

        $this->assertNotFalse($hash);
        $this->assertNotEmpty($hash, 'Empty file should still produce a hash');

        unlink($tempFile);
    }

    public function testHashAndHashFileProduceDifferentResults(): void
    {
        $content = 'test content';
        $tempFile = tempnam(sys_get_temp_dir(), 'hash_test_');
        file_put_contents($tempFile, $content);

        $stringHash = HashUtility::hash($content);
        $fileHash = HashUtility::hashFile($tempFile);

        // Both should hash the same content, so they should be equal
        $this->assertSame($stringHash, $fileHash, 'String hash and file hash of same content should match');

        unlink($tempFile);
    }
}
