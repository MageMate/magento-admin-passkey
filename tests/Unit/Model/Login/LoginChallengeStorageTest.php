<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Model\Login;

use MageMate\AdminPasskey\Model\Login\LoginChallengeStorage;
use Magento\Backend\Model\Session;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Model\Login\LoginChallengeStorage
 */
class LoginChallengeStorageTest extends TestCase
{
    private const KEY = 'magemate_passkey_login_challenge';

    /**
     * @var Session&MockObject
     */
    private $session;

    private LoginChallengeStorage $storage;

    protected function setUp(): void
    {
        // setData/unsetData are magic (SessionManager::__call); declare them for mocking.
        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->addMethods(['setData', 'unsetData'])
            ->getMock();

        $this->storage = new LoginChallengeStorage($this->session);
    }

    public function testStorePersistsChallenge(): void
    {
        $this->session->expects($this->once())
            ->method('setData')
            ->with(self::KEY, 'chal');

        $this->storage->store('chal');
    }

    public function testGetReturnsStoredChallenge(): void
    {
        $this->session->method('getData')->with(self::KEY)->willReturn('chal');

        $this->assertSame('chal', $this->storage->get());
    }

    public function testGetReturnsNullWhenAbsentOrBlank(): void
    {
        $this->session->method('getData')->with(self::KEY)->willReturn('');

        $this->assertNull($this->storage->get());
    }

    public function testClearUnsetsSessionKey(): void
    {
        $this->session->expects($this->once())->method('unsetData')->with(self::KEY);

        $this->storage->clear();
    }
}
