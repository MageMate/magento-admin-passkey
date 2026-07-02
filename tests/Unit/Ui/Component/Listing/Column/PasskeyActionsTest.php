<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Ui\Component\Listing\Column;

use MageMate\AdminPasskey\Model\Passkey\Source\Status;
use MageMate\AdminPasskey\Ui\Component\Listing\Column\PasskeyActions;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\User\Model\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Ui\Component\Listing\Column\PasskeyActions
 */
class PasskeyActionsTest extends TestCase
{
    /**
     * @var UrlInterface&MockObject
     */
    private $urlBuilder;

    /**
     * @var AuthSession&MockObject
     */
    private $authSession;

    /**
     * @var PasskeyActions
     */
    private PasskeyActions $column;

    protected function setUp(): void
    {
        $context = $this->createMock(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createMock(Processor::class));

        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $path): string => 'https://admin.test/' . $path
        );

        // getUser is magic (Session\SessionManager::__call); declare it for mocking.
        $this->authSession = $this->getMockBuilder(AuthSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getUser'])
            ->getMock();

        $this->column = new PasskeyActions(
            $context,
            $this->createMock(UiComponentFactory::class),
            $this->urlBuilder,
            $this->authSession,
            [],
            ['name' => 'actions']
        );
    }

    public function testExpiredOwnRowGetsReRegisterPrompt(): void
    {
        $this->mockUser(7);

        $result = $this->column->prepareDataSource([
            'data' => ['items' => [$this->row(1, 7, Status::EXPIRED)]],
        ]);
        $actions = $result['data']['items'][0]['actions'];

        $this->assertArrayHasKey('reregister', $actions);
        $this->assertSame('https://admin.test/passkey/register/index', $actions['reregister']['href']);
        $this->assertArrayHasKey('delete', $actions);
    }

    public function testActiveOwnRowHasNoReRegisterPrompt(): void
    {
        $this->mockUser(7);

        $result = $this->column->prepareDataSource([
            'data' => ['items' => [$this->row(1, 7, Status::ACTIVE)]],
        ]);

        $this->assertArrayNotHasKey('reregister', $result['data']['items'][0]['actions']);
    }

    public function testExpiredOtherUsersRowHasNoReRegisterPrompt(): void
    {
        $this->mockUser(7);

        $result = $this->column->prepareDataSource([
            'data' => ['items' => [$this->row(1, 99, Status::EXPIRED)]],
        ]);

        $this->assertArrayNotHasKey('reregister', $result['data']['items'][0]['actions']);
    }

    public function testNoSessionUserSuppressesReRegisterPrompt(): void
    {
        $this->authSession->method('getUser')->willReturn(null);

        $result = $this->column->prepareDataSource([
            'data' => ['items' => [$this->row(1, 7, Status::EXPIRED)]],
        ]);

        $this->assertArrayNotHasKey('reregister', $result['data']['items'][0]['actions']);
    }

    /**
     * Configure the auth session to return an admin user with the given id.
     *
     * @param int $userId
     * @return void
     */
    private function mockUser(int $userId): void
    {
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $user->method('getId')->willReturn($userId);
        $this->authSession->method('getUser')->willReturn($user);
    }

    /**
     * Build a grid row item.
     *
     * @param int $passkeyId
     * @param int $userId
     * @param string $status
     * @return array
     */
    private function row(int $passkeyId, int $userId, string $status): array
    {
        return [
            'passkey_id' => $passkeyId,
            'user_id' => $userId,
            'label' => 'My Passkey',
            'status' => $status,
        ];
    }
}
