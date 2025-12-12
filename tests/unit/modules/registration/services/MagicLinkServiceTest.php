<?php

namespace Tests\Unit\Modules\Registration\Services;

use Codeception\Test\Unit;
use Tests\Support\UnitTester;
use modules\registration\services\MagicLinkService;
use modules\registration\records\MagicLinkTokenRecord;
use Craft;
use DateTime;

class MagicLinkServiceTest extends Unit
{
    protected UnitTester $tester;

    protected function _before()
    {
        parent::_before();
        
        // Clean up any existing test tokens
        MagicLinkTokenRecord::deleteAll();
    }

    protected function _after()
    {
        // Clean up after each test
        MagicLinkTokenRecord::deleteAll();
    }

    public function testGenerateTokenCreatesValidToken()
    {
        $email = 'test@example.com';
        $token = MagicLinkService::generateToken($email);
        
        // Verify token format
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        
        // Verify token was saved to database
        $record = MagicLinkTokenRecord::find()
            ->where(['email' => $email, 'token' => $token])
            ->one();
            
        $this->assertNotNull($record);
        $this->assertEquals($email, $record->email);
        
        // Verify expiration is set correctly (approximately 15 minutes from now)
        $expiresAt = new DateTime($record->expiresAt);
        $expectedExpiry = new DateTime('+15 minutes');
        $timeDiff = abs($expiresAt->getTimestamp() - $expectedExpiry->getTimestamp());
        $this->assertLessThan(60, $timeDiff); // Within 1 minute tolerance
    }

    public function testValidateTokenWithValidToken()
    {
        $email = 'test@example.com';
        $token = MagicLinkService::generateToken($email);
        
        $result = MagicLinkService::validateToken($token);
        
        $this->assertEquals($email, $result);
    }

    public function testValidateTokenWithInvalidToken()
    {
        $result = MagicLinkService::validateToken('invalid-token-that-does-not-exist');
        
        $this->assertNull($result);
    }

    public function testValidateTokenWithExpiredToken()
    {
        // Create an expired token manually
        $email = 'test@example.com';
        $token = bin2hex(random_bytes(32));
        
        $record = new MagicLinkTokenRecord();
        $record->email = $email;
        $record->token = $token;
        $record->expiresAt = (new DateTime('-1 hour'))->format('Y-m-d H:i:s');
        $record->save();
        
        $result = MagicLinkService::validateToken($token);
        
        $this->assertNull($result);
    }

    public function testValidateTokenWithUsedToken()
    {
        // Create a used token manually
        $email = 'test@example.com';
        $token = bin2hex(random_bytes(32));
        
        $record = new MagicLinkTokenRecord();
        $record->email = $email;
        $record->token = $token;
        $record->expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
        $record->usedAt = (new DateTime())->format('Y-m-d H:i:s');
        $record->save();
        
        $result = MagicLinkService::validateToken($token);
        
        $this->assertNull($result);
    }

    public function testMarkTokenUsed()
    {
        $email = 'test@example.com';
        $token = MagicLinkService::generateToken($email);
        
        // Verify token is initially unused
        $record = MagicLinkTokenRecord::find()
            ->where(['token' => $token])
            ->one();
        $this->assertNull($record->usedAt);
        
        // Mark token as used
        MagicLinkService::markTokenUsed($token);
        
        // Verify token is now marked as used
        $record->refresh();
        $this->assertNotNull($record->usedAt);
        
        // Verify used token can no longer be validated
        $result = MagicLinkService::validateToken($token);
        $this->assertNull($result);
    }

    public function testCleanupExpiredTokens()
    {
        // Create some test tokens
        $validToken = MagicLinkService::generateToken('valid@example.com');
        
        // Create an expired token
        $expiredRecord = new MagicLinkTokenRecord();
        $expiredRecord->email = 'expired@example.com';
        $expiredRecord->token = bin2hex(random_bytes(32));
        $expiredRecord->expiresAt = (new DateTime('-2 hours'))->format('Y-m-d H:i:s');
        $expiredRecord->save();
        
        // Create an old used token
        $oldUsedRecord = new MagicLinkTokenRecord();
        $oldUsedRecord->email = 'oldused@example.com';
        $oldUsedRecord->token = bin2hex(random_bytes(32));
        $oldUsedRecord->expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
        $oldUsedRecord->usedAt = (new DateTime('-25 hours'))->format('Y-m-d H:i:s');
        $oldUsedRecord->save();
        
        // Verify we have 3 tokens before cleanup
        $this->assertEquals(3, MagicLinkTokenRecord::find()->count());
        
        // Run cleanup
        MagicLinkService::cleanupExpiredTokens();
        
        // Verify only the valid token remains
        $remainingTokens = MagicLinkTokenRecord::find()->all();
        $this->assertEquals(1, count($remainingTokens));
        $this->assertEquals($validToken, $remainingTokens[0]->token);
    }
}