<?php

declare(strict_types=1);

namespace Pinterest\PinterestMagento2Extension\Cron;

use Pinterest\PinterestMagento2Extension\Helper\PinterestHelper;
use Pinterest\PinterestMagento2Extension\Helper\TokensHelper;
use PHPUnit\Framework\TestCase;

class RefreshTokensTest extends TestCase
{
    /**
     * @var PinterestHelper
     */
    protected $_pinterestHelper;

    /**
     * @var TokensHelper
     */
    protected $_tokensHelper;

    /**
     * @var RefreshTokens
     */
    protected $_refreshTokens;

    /**
     * Used to set the values before running a test
     *
     * @return void
     */
    public function setUp() : void
    {
        $this->_pinterestHelper = $this->createMock(PinterestHelper::class);
        $this->_tokensHelper = $this->createMock(TokensHelper::class);

        $this->_refreshTokens = new RefreshTokens(
            $this->_pinterestHelper,
            $this->_tokensHelper
        );
    }

    /**
     * Test execute when user is not connected and multistore is off
     */
    public function testExecuteWhenUserNotConnected()
    {
        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(2))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(false);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(false);

        $this->_tokensHelper->expects($this->never())
            ->method('refreshTokens');

        $this->_tokensHelper->expects($this->never())
            ->method('refreshStoreToken');

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[1]);
    }

    /**
     * Test execute when user is connected, refresh is successful, and multistore is off
     */
    public function testExecuteWhenUserConnectedAndRefreshSuccessfulMultistoreOff()
    {
        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(4))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(false);

        $this->_tokensHelper->expects($this->once())
            ->method('refreshTokens')
            ->willReturn(true);

        $this->_tokensHelper->expects($this->never())
            ->method('refreshStoreToken');

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Pinterest token refresh cron job calling API', $logInfoCalls[1]);
        $this->assertEquals('Job to refresh Pinterest tokens successful.', $logInfoCalls[2]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[3]);
    }

    /**
     * Test execute when user is connected, refresh fails, and multistore is off
     */
    public function testExecuteWhenUserConnectedAndRefreshFailsMultistoreOff()
    {
        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(3))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(false);

        $this->_tokensHelper->expects($this->once())
            ->method('refreshTokens')
            ->willReturn(false);

        $this->_pinterestHelper->expects($this->once())
            ->method('logError')
            ->with('Job to refresh Pinterest tokens failed.');

        $this->_tokensHelper->expects($this->never())
            ->method('refreshStoreToken');

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Pinterest token refresh cron job calling API', $logInfoCalls[1]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[2]);
    }

    /**
     * Test execute when user is connected, multistore is on, and all store refreshes are successful
     */
    public function testExecuteWhenMultistoreOnAndAllStoreRefreshesSuccessful()
    {
        $storeIds = [1, 2, 3];

        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(7))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('getMappedStores')
            ->willReturn($storeIds);

        $this->_tokensHelper->expects($this->once())
            ->method('refreshTokens')
            ->willReturn(true);

        $refreshStoreTokenCalls = [];
        $this->_tokensHelper->expects($this->exactly(3))
            ->method('refreshStoreToken')
            ->willReturnCallback(function ($storeId) use (&$refreshStoreTokenCalls) {
                $refreshStoreTokenCalls[] = $storeId;
                return true;
            });

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Pinterest token refresh cron job calling API', $logInfoCalls[1]);
        $this->assertEquals('Job to refresh Pinterest tokens successful.', $logInfoCalls[2]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 1', $logInfoCalls[3]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 2', $logInfoCalls[4]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 3', $logInfoCalls[5]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[6]);

        $this->assertEquals([1, 2, 3], $refreshStoreTokenCalls);
    }

    /**
     * Test execute when user is connected, multistore is on, and all store refreshes fail
     */
    public function testExecuteWhenMultistoreOnAndAllStoreRefreshesFail()
    {
        $storeIds = [1, 2];

        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(4))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $logErrorCalls = [];
        $this->_pinterestHelper->expects($this->exactly(2))
            ->method('logError')
            ->willReturnCallback(function ($message) use (&$logErrorCalls) {
                $logErrorCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('getMappedStores')
            ->willReturn($storeIds);

        $this->_tokensHelper->expects($this->once())
            ->method('refreshTokens')
            ->willReturn(true);

        $refreshStoreTokenCalls = [];
        $this->_tokensHelper->expects($this->exactly(2))
            ->method('refreshStoreToken')
            ->willReturnCallback(function ($storeId) use (&$refreshStoreTokenCalls) {
                $refreshStoreTokenCalls[] = $storeId;
                return false;
            });

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Pinterest token refresh cron job calling API', $logInfoCalls[1]);
        $this->assertEquals('Job to refresh Pinterest tokens successful.', $logInfoCalls[2]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[3]);

        $this->assertEquals('Job to refresh Pinterest tokens failed. Store: 1', $logErrorCalls[0]);
        $this->assertEquals('Job to refresh Pinterest tokens failed. Store: 2', $logErrorCalls[1]);

        $this->assertEquals([1, 2], $refreshStoreTokenCalls);
    }

    /**
     * Test execute when user is connected, multistore is on, and store refreshes have mixed results
     */
    public function testExecuteWhenMultistoreOnAndStoreRefreshesMixed()
    {
        $storeIds = [1, 2, 3];

        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(6))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('logError')
            ->with('Job to refresh Pinterest tokens failed. Store: 2');

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('getMappedStores')
            ->willReturn($storeIds);

        $this->_tokensHelper->expects($this->once())
            ->method('refreshTokens')
            ->willReturn(true);

        $refreshStoreTokenCalls = [];
        $this->_tokensHelper->expects($this->exactly(3))
            ->method('refreshStoreToken')
            ->willReturnCallback(function ($storeId) use (&$refreshStoreTokenCalls) {
                $refreshStoreTokenCalls[] = $storeId;
                return $storeId !== 2; // Return false for store 2, true for others
            });

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Pinterest token refresh cron job calling API', $logInfoCalls[1]);
        $this->assertEquals('Job to refresh Pinterest tokens successful.', $logInfoCalls[2]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 1', $logInfoCalls[3]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 3', $logInfoCalls[4]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[5]);

        $this->assertEquals([1, 2, 3], $refreshStoreTokenCalls);
    }

    /**
     * Test execute when user is not connected but multistore is on
     */
    public function testExecuteWhenUserNotConnectedButMultistoreOn()
    {
        $storeIds = [1, 2];

        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(4))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(false);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('getMappedStores')
            ->willReturn($storeIds);

        $this->_tokensHelper->expects($this->never())
            ->method('refreshTokens');

        $refreshStoreTokenCalls = [];
        $this->_tokensHelper->expects($this->exactly(2))
            ->method('refreshStoreToken')
            ->willReturnCallback(function ($storeId) use (&$refreshStoreTokenCalls) {
                $refreshStoreTokenCalls[] = $storeId;
                return true;
            });

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 1', $logInfoCalls[1]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 2', $logInfoCalls[2]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[3]);

        $this->assertEquals([1, 2], $refreshStoreTokenCalls);
    }

    /**
     * Test execute when main token refresh fails but multistore refreshes succeed
     */
    public function testExecuteWhenMainRefreshFailsButMultistoreSucceeds()
    {
        $storeIds = [1];

        $logInfoCalls = [];
        $this->_pinterestHelper->expects($this->exactly(4))
            ->method('logInfo')
            ->willReturnCallback(function ($message) use (&$logInfoCalls) {
                $logInfoCalls[] = $message;
            });

        $this->_pinterestHelper->expects($this->once())
            ->method('logError')
            ->with('Job to refresh Pinterest tokens failed.');

        $this->_pinterestHelper->expects($this->once())
            ->method('isUserConnected')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('isMultistoreOn')
            ->willReturn(true);

        $this->_pinterestHelper->expects($this->once())
            ->method('getMappedStores')
            ->willReturn($storeIds);

        $this->_tokensHelper->expects($this->once())
            ->method('refreshTokens')
            ->willReturn(false);

        $this->_tokensHelper->expects($this->once())
            ->method('refreshStoreToken')
            ->with(1)
            ->willReturn(true);

        $this->_refreshTokens->execute();

        $this->assertEquals('Pinterest token refresh cron job started', $logInfoCalls[0]);
        $this->assertEquals('Pinterest token refresh cron job calling API', $logInfoCalls[1]);
        $this->assertEquals('Job to refresh Pinterest tokens successful. Store: 1', $logInfoCalls[2]);
        $this->assertEquals('Pinterest token refresh cron job ended', $logInfoCalls[3]);
    }
}