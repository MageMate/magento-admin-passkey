<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Registration;

use MageMate\AdminPasskey\Model\Registration\ChallengeStorage;
use Magento\Backend\Model\Session;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Registration\ChallengeStorage
 */
class ChallengeStorageTest extends TestCase
{
    private const KEY = 'magemate_passkey_registration';

    /**
     * @var Session&MockObject
     */
    private $session;

    private ChallengeStorage $storage;

    protected function setUp(): void
    {
        // setData/unsetData are magic (SessionManager::__call); declare them for mocking.
        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->addMethods(['setData', 'unsetData'])
            ->getMock();

        $this->storage = new ChallengeStorage($this->session);
    }

    public function testStorePersistsUserBoundChallenge(): void
    {
        $this->session->expects($this->once())
            ->method('setData')
            ->with(self::KEY, ['user_id' => 7, 'challenge' => 'chal']);

        $this->storage->store(7, 'chal');
    }

    public function testGetReturnsChallengeForMatchingUser(): void
    {
        $this->session->method('getData')
            ->with(self::KEY)
            ->willReturn(['user_id' => 7, 'challenge' => 'chal']);

        $this->assertSame('chal', $this->storage->get(7));
    }

    public function testGetRejectsChallengeIssuedForAnotherUser(): void
    {
        $this->session->method('getData')
            ->with(self::KEY)
            ->willReturn(['user_id' => 7, 'challenge' => 'chal']);

        $this->assertNull($this->storage->get(8));
    }

    public function testGetReturnsNullWhenAbsent(): void
    {
        $this->session->method('getData')->with(self::KEY)->willReturn(null);

        $this->assertNull($this->storage->get(7));
    }

    public function testClearUnsetsSessionKey(): void
    {
        $this->session->expects($this->once())->method('unsetData')->with(self::KEY);

        $this->storage->clear();
    }
}
