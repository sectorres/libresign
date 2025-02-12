<?php

namespace OCA\Libresign\Tests\Unit\Service;

use OC\AppFramework\Utility\TimeFactory;
use OC\Http\Client\ClientService;
use OCA\Libresign\Db\AccountFileMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Db\UserElementMapper;
use OCA\Libresign\Handler\Pkcs12Handler;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\AccountFileService;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\SignatureService;
use OCA\Libresign\Service\SignFileService;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccountManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 * @group DB
 */
final class AccountServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/** @var IL10N\|MockObject */
	private $l10n;
	/** @var FileUserMapper|MockObject */
	private $fileUserMapper;
	/** @var IUserManager|MockObject */
	private $userManagerInstance;
	/** @var IAccountManager */
	private $accountManager;
	/** @var IRootFolder|MockObject */
	private $root;
	/** @var FileMapper|MockObject */
	private $fileMapper;
	/** @var FileTypeMapper|MockObject */
	private $fileTypeMapper;
	/** @var AccountFileMapper|MockObject */
	private $accountFileMapper;
	/** @var SignFileService|MockObject */
	private $signFile;
	/** @var IConfig|MockObject */
	private $config;
	/** @var NewUserMailHelper|MockObject */
	private $newUserMail;
	/** @var ValidateHelper|MockObject */
	private $validateHelper;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var IGroupManager|MockObject */
	private $groupManager;
	/** @var AccountFileService */
	private $accountFileService;
	/** @var UserElementMapper */
	private $userElementMapper;
	/** @var FolderService */
	private $folderService;
	/** @var ClientService */
	private $clientService;
	/** @var TimeFactory|MockObject */
	private $timeFactory;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$this->fileUserMapper = $this->createMock(FileUserMapper::class);
		$this->userManagerInstance = $this->createMock(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->fileTypeMapper = $this->createMock(FileTypeMapper::class);
		$this->accountFileMapper = $this->createMock(AccountFileMapper::class);
		$this->signFile = $this->createMock(SignFileService::class);
		$this->signatureService = $this->createMock(SignatureService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->newUserMail = $this->createMock(NewUserMailHelper::class);
		$this->validateHelper = $this->createMock(ValidateHelper::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->pkcs12Handler = $this->createMock(Pkcs12Handler::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->accountFileService = $this->createMock(AccountFileService::class);
		$this->userElementMapper = $this->createMock(UserElementMapper::class);
		$this->folderService = $this->createMock(FolderService::class);
		$this->clientService = $this->createMock(ClientService::class);
		$this->timeFactory = $this->createMock(TimeFactory::class);
	}

	private function getService(): AccountService {
		return new AccountService(
			$this->l10n,
			$this->fileUserMapper,
			$this->userManagerInstance,
			$this->accountManager,
			$this->root,
			$this->fileMapper,
			$this->fileTypeMapper,
			$this->accountFileMapper,
			$this->signFile,
			$this->signatureService,
			$this->config,
			$this->newUserMail,
			$this->validateHelper,
			$this->urlGenerator,
			$this->pkcs12Handler,
			$this->groupManager,
			$this->accountFileService,
			$this->userElementMapper,
			$this->folderService,
			$this->clientService,
			$this->timeFactory
		);
	}

	/**
	 * @dataProvider providerTestValidateCreateToSignUsingDataProvider
	 */
	public function testValidateCreateToSignUsingDataProvider($arguments, $expectedErrorMessage) {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCreateToSign($arguments);
	}

	public function providerTestValidateCreateToSignUsingDataProvider() {
		return [
			[ #0
				[
					'uuid' => 'invalid uuid'
				],
				'Invalid UUID'
			],
			[ #1
				function ($self) {
					$uuid = '12345678-1234-1234-1234-123456789012';
					$self->fileUserMapper = $self->createMock(FileUserMapper::class);
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnCallback(function () {
							throw new \Exception("Beep, beep, not found!", 1);
						}));
					return [
						'uuid' => $uuid
					];
				},
				'UUID not found'
			],
			[ #2
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'invalid@test.coop',
						],
						'signPassword' => '132456789'
					];
				},
				'This is not your file'
			],
			[ #3
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					$self->userManagerInstance
						->method('userExists')
						->will($self->returnValue(true));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'valid@test.coop',
						],
						'signPassword' => '123456789'
					];
				},
				'User already exists'
			],
			[ #4
				function ($self) {
					$fileUser = $self->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->with($self->equalTo('getEmail'), $self->anything())
						->will($self->returnValue('valid@test.coop'));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'valid@test.coop',
						],
						'signPassword' => '132456789',
						'password' => ''
					];
				},
				'Password is mandatory'
			],
			[ #5
				function ($self) {
					$fileUser = $this->createMock(FileUser::class);
					$fileUser
						->method('__call')
						->withConsecutive(
							[$this->equalTo('getEmail')],
							[$this->equalTo('getFileId')],
							[$this->equalTo('getUserId')],
						)
						->will($this->returnValueMap([
							['getEmail', [], 'valid@test.coop'],
							['getFileId', [], 171],
							['getUserId', [], 'username'],
						]));
					$file = $this->createMock(\OCA\Libresign\Db\File::class);
					$self->fileMapper
						->method('getById')
						->will($self->returnValue($file));
					$self->fileUserMapper
						->method('getByUuid')
						->will($self->returnValue($fileUser));

					$self->root
						->method('getById')
						->will($self->returnValue([]));
					$folder = $this->createMock(\OCP\Files\Folder::class);
					$folder
						->method('getById')
						->willReturn([]);
					$self->root
						->method('getUserFolder')
						->willReturn($folder);
					return [
						'uuid' => '12345678-1234-1234-1234-123456789012',
						'user' => [
							'email' => 'valid@test.coop',
						],
						'signPassword' => '132456789',
						'password' => '123456789'
					];
				},
				'File not found'
			],
		];
	}

	/**
	 * @dataProvider providerTestValidateCertificateDataUsingDataProvider
	 */
	public function testValidateCertificateDataUsingDataProvider($arguments, $expectedErrorMessage) {
		if (is_callable($arguments)) {
			$arguments = $arguments($this);
		}

		$this->expectExceptionMessage($expectedErrorMessage);
		$this->getService()->validateCertificateData($arguments);
	}

	public function providerTestValidateCertificateDataUsingDataProvider() {
		return [
			[
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => '',
					],
				],
				'You must have an email. You can define the email in your profile.'
			],
			[
				[
					'uuid' => '12345678-1234-1234-1234-123456789012',
					'user' => [
						'email' => 'invalid',
					],
				],
				'Invalid email'
			]
		];
	}

	public function testValidateCertificateDataWithSuccess() {
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->with($this->equalTo('getEmail'), $this->anything())
			->will($this->returnValue('valid@test.coop'));
		$this->fileUserMapper
			->method('getByUuid')
			->will($this->returnValue($fileUser));
		$actual = $this->getService()->validateCertificateData([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'user' => [
				'email' => 'valid@test.coop',
			],
			'password' => '123456789',
			'signPassword' => '123456',
		]);
		$this->assertNull($actual);
	}

	private function mockValidateWithSuccess() {
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getEmail')],
				[$this->equalTo('getFileId')],
				[$this->equalTo('getUserId')],
				[$this->equalTo('getNodeId')],
			)
			->will($this->returnValueMap([
				['getEmail', [], 'valid@test.coop'],
				['getFileId', [], 171],
				['getUserId', [], 'username'],
				['getNodeId', [], 171],
			]));
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$this->fileMapper
			->method('getById')
			->will($this->returnValue($libresignFile));
		$this->fileUserMapper
			->method('getByUuid')
			->will($this->returnValue($fileUser));

		$this->root
			->method('getById')
			->will($this->returnValue(['fileToSign']));
		$file = $this->createMock(\OCP\Files\File::class);
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder
			->method('getById')
			->willReturn([$file]);
		$this->root
			->method('getUserFolder')
			->willReturn($folder);
	}

	public function testValidateCreateToSignSuccess() {
		$this->mockValidateWithSuccess();

		$actual = $this->getService()->validateCreateToSign([
			'uuid' => '12345678-1234-1234-1234-123456789012',
			'user' => [
				'email' => 'valid@test.coop',
			],
			'password' => '123456789',
			'signPassword' => '123456789',
		]);
		$this->assertNull($actual);
	}

	public function testCreateToSignWithErrorInSendingEmail() {
		$fileUser = $this->createMock(\OCA\Libresign\Db\FileUser::class);
		$fileUser
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getDisplayName')],
				[$this->equalTo('getEmail')]
			)
			->will($this->returnValueMap([
				['getDisplayName', [], 'John Doe'],
				['getEmail', [], 'valid@test.coop']
			]));
		$this->fileUserMapper->method('getByUuid')->will($this->returnValue($fileUser));
		$userToSign = $this->createMock(\OCP\IUser::class);
		$this->userManagerInstance->method('createUser')->will($this->returnValue($userToSign));
		$this->config->method('getAppValue')->will($this->returnValue('yes'));
		$template = $this->createMock(\OCP\Mail\IEMailTemplate::class);
		$this->newUserMail->method('generateTemplate')->will($this->returnValue($template));
		$this->newUserMail->method('sendMail')->will($this->returnCallback(function () {
			throw new \Exception("Error Processing Request", 1);
		}));
		$this->expectErrorMessage('Unable to send the invitation');
		$this->getService()->createToSign('uuid', 'username', 'passwordOfUser', 'passwordToSign');
	}

	public function testGetPdfByUuidWithSuccessAndSignedFile() {
		$this->createUser('username', 'password');

		$node = $this->createMock(\OCP\Files\File::class);
		$node->method('getId')->will($this->returnValue(171));

		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file
			->expects($this->exactly(3))
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getUserId')],
				[$this->equalTo('getStatus')],
				[$this->equalTo('getSignedNodeId')]
			)
			->will($this->returnValueMap([
				['getUserId', [], 'username'],
				['getStatus', [], \OCA\Libresign\Db\File::STATUS_SIGNED],
				['getSignedNodeId', [], [$node]]
			]));
		$this->fileMapper
			->method('getByUuid')
			->will($this->returnValue($file));

		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder
			->method('getById')
			->willReturn([$node]);
		$this->root
			->method('getUserFolder')
			->willReturn($folder);

		$actual = $this->getService()->getPdfByUuid('uuid');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
	}

	public function testGetPdfByUuidWithSuccessAndUnignedFile() {
		$this->createUser('username', 'password');

		$node = $this->createMock(\OCP\Files\File::class);
		$node->method('getId')->will($this->returnValue(171));

		$file = $this->createMock(\OCA\Libresign\Db\File::class);
		$file
			->expects($this->exactly(3))
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getUserId')],
				[$this->equalTo('getStatus')],
				[$this->equalTo('getNodeId')]
			)
			->will($this->returnValueMap([
				['getUserId', [], 'username'],
				['getStatus', [], \OCA\Libresign\Db\File::STATUS_PARTIAL_SIGNED],
				['getNodeId', [], [$node]]
			]));
		$this->fileMapper
			->method('getByUuid')
			->will($this->returnValue($file));

		$node = $this->createMock(\OCP\Files\File::class);
		$node->method('getId')->will($this->returnValue(171));

		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->with($this->equalTo('getSigned'))
			->willReturn(true);
		$this->fileUserMapper
			->method('getByFileId')
			->willReturn([$fileUser]);
		$folder = $this->createMock(\OCP\Files\Folder::class);
		$folder
			->method('getById')
			->willReturn([$node]);
		$this->root
			->method('getUserFolder')
			->willReturn($folder);

		$actual = $this->getService()->getPdfByUuid('uuid');
		$this->assertInstanceOf(\OCP\Files\File::class, $actual);
	}

	public function testCanRequestSignWithUnexistentUser() {
		$actual = $this->getService()->canRequestSign();
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithoutGroups() {
		$this->config
			->method('getAppValue')
			->willReturn(null);
		$user = $this->createMock(\OCP\IUser::class);
		$actual = $this->getService()->canRequestSign($user);
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithUserOutOfAuthorizedGroups() {
		$this->config
			->method('getAppValue')
			->willReturn('["admin"]');
		$this->groupManager
			->method('getUserGroupIds')
			->willReturn([]);
		$user = $this->createMock(\OCP\IUser::class);
		$actual = $this->getService()->canRequestSign($user);
		$this->assertFalse($actual);
	}

	public function testCanRequestSignWithSuccess() {
		$this->config
			->method('getAppValue')
			->willReturn('["admin"]');
		$this->groupManager
			->method('getUserGroupIds')
			->willReturn(['admin']);
		$user = $this->createMock(\OCP\IUser::class);
		$actual = $this->getService()->canRequestSign($user);
		$this->assertTrue($actual);
	}

	public function testAccountvalidateWithSuccess() {
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(["IDENTIFICATION" => ["type" => "IDENTIFICATION"]]);
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')
			->willReturn('username');
		$actual = $this->getService()->validateAccountFiles([
			[
				'type' => 'IDENTIFICATION',
				'file' => [
					'base64' => 'dGVzdA=='
				]
			]
		], $user);
		$this->assertNull($actual);
	}

	public function testAccountvalidateWithInvalidFileType() {
		$this->expectExceptionMessage('Invalid file type.');
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(["IDENTIFICATION" => ["type" => "IDENTIFICATION"]]);
		$user = $this->createMock(\OCP\IUser::class);
		$this->getService()->validateAccountFiles([
			[
				'type' => 'invalid',
				'file' => [
					'base64' => 'invalid'
				]
			]
		], $user);
	}

	public function testAddFilesToAccountWithSuccess() {
		$this->fileTypeMapper
			->method('getTypes')
			->willReturn(["IDENTIFICATION" => ["type" => "IDENTIFICATION"]]);
		$files = [
			[
				'type' => 'IDENTIFICATION',
				'file' => [
					'base64' => base64_encode(file_get_contents(__DIR__ . '/../../fixtures/small_valid.pdf'))
				]
			]
		];
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')
			->willReturn('username');
		$return = $this->getService()->addFilesToAccount($files, $user);
		$this->assertNull($return);
	}
}
