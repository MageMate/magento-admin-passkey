<?php
/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */
declare(strict_types=1);

namespace MageMate\AdminPasskey\Test\Unit\Observer;

use MageMate\AdminPasskey\Controller\Adminhtml\Register\Index as RegisterIndex;
use MageMate\AdminPasskey\Model\ForceSetup\SetupRequirement;
use MageMate\AdminPasskey\Observer\ForcePasskeySetup;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageMate\AdminPasskey\Observer\ForcePasskeySetup
 */
class ForcePasskeySetupTest extends TestCase
{
    private const REGISTER_URL = 'https://admin.example.com/passkey/register';

    /**
     * @var SetupRequirement&MockObject
     */
    private $setupRequirement;

    /**
     * @var UserContextInterface&MockObject
     */
    private $userContext;

    /**
     * @var AuthorizationInterface&MockObject
     */
    private $authorization;

    /**
     * @var ActionFlag&MockObject
     */
    private $actionFlag;

    /**
     * @var UrlInterface&MockObject
     */
    private $url;

    /**
     * @var HttpResponse&MockObject
     */
    private $response;

    /**
     * @var ForcePasskeySetup
     */
    private ForcePasskeySetup $observer;

    protected function setUp(): void
    {
        $this->setupRequirement = $this->createMock(SetupRequirement::class);
        $this->userContext = $this->createMock(UserContextInterface::class);
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->actionFlag = $this->createMock(ActionFlag::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->response = $this->getMockBuilder(HttpResponse::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setRedirect'])
            ->getMock();

        $this->observer = new ForcePasskeySetup(
            $this->setupRequirement,
            $this->userContext,
            $this->authorization,
            $this->actionFlag,
            $this->url
        );
    }

    public function testRedirectsWhenSetupRequired(): void
    {
        $this->authorization->method('isAllowed')
            ->with(RegisterIndex::ADMIN_RESOURCE)->willReturn(true);
        $this->userContext->method('getUserId')->willReturn(7);
        $this->setupRequirement->method('isRequiredFor')->with(7)->willReturn(true);
        $this->url->method('getUrl')->with('passkey/register')->willReturn(self::REGISTER_URL);

        $this->actionFlag->expects($this->once())
            ->method('set')->with('', Action::FLAG_NO_DISPATCH, true);
        $this->response->expects($this->once())
            ->method('setRedirect')->with(self::REGISTER_URL);

        $this->observer->execute($this->buildObserver('adminhtml_dashboard_index'));
    }

    public function testDoesNotRedirectWhenNotRequired(): void
    {
        $this->authorization->method('isAllowed')->willReturn(true);
        $this->userContext->method('getUserId')->willReturn(7);
        $this->setupRequirement->method('isRequiredFor')->with(7)->willReturn(false);

        $this->actionFlag->expects($this->never())->method('set');
        $this->response->expects($this->never())->method('setRedirect');

        $this->observer->execute($this->buildObserver('adminhtml_dashboard_index'));
    }

    public function testDoesNotRedirectWhenUserCannotRegister(): void
    {
        $this->authorization->method('isAllowed')->willReturn(false);
        $this->setupRequirement->expects($this->never())->method('isRequiredFor');

        $this->actionFlag->expects($this->never())->method('set');

        $this->observer->execute($this->buildObserver('adminhtml_dashboard_index'));
    }

    /**
     * @return string[][]
     */
    public static function allowListedActionProvider(): array
    {
        return [
            'own register page' => ['passkey_register_index'],
            'own login options' => ['passkey_login_options'],
            'own manage grid' => ['passkey_manage_index'],
            'tfa index' => ['tfa_tfa_index'],
            'tfa configure' => ['tfa_tfa_configure'],
            'admin login' => ['adminhtml_auth_login'],
            'admin logout' => ['adminhtml_auth_logout'],
            'forgot password' => ['adminhtml_auth_forgotpassword'],
        ];
    }

    /**
     * @dataProvider allowListedActionProvider
     * @param string $fullActionName
     */
    public function testAllowListedActionsAreNeverIntercepted(string $fullActionName): void
    {
        $this->authorization->expects($this->never())->method('isAllowed');
        $this->setupRequirement->expects($this->never())->method('isRequiredFor');
        $this->actionFlag->expects($this->never())->method('set');

        $this->observer->execute($this->buildObserver($fullActionName));
    }

    /**
     * Build an observer whose event carries the given full action name.
     *
     * @param string $fullActionName
     * @return Observer
     */
    private function buildObserver(string $fullActionName): Observer
    {
        $request = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFullActionName'])
            ->getMock();
        $request->method('getFullActionName')->willReturn($fullActionName);

        $controllerAction = $this->getMockBuilder(Action::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResponse', 'execute'])
            ->getMock();
        $controllerAction->method('getResponse')->willReturn($this->response);

        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $event->method('getData')->willReturnMap([
            ['request', null, $request],
            ['controller_action', null, $controllerAction],
        ]);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }
}
