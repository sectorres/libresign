<?php

namespace OCA\Libresign\Tests\Unit\Controller;

use OCA\Libresign\Controller\AccountController;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Helper\JSActions;
use OCA\Libresign\Service\AccountService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\File as FileNode;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class AccountControllerTest extends TestCase {
	/** @var IRequest */
	private $request;
	/** @var IL10N */
	private $l10n;
	/** @var AccountService */
	private $account;
	/** @var FileMapper */
	private $fileMapper;
	/** @var IRootFolder */
	private $root;

	public function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->account = $this->createMock(AccountService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->controller = new AccountController(
			$this->request,
			$this->l10n,
			$this->account,
			$this->fileMapper,
			$this->root
		);
	}

	public function testCreateSuccess() {
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
		$fileUser = $this->createMock(FileUser::class);
		$fileUser
			->method('__call')
			->with($this->equalTo('getLibresignFileId'), $this->anything())
			->will($this->returnValue(1));
		$this->account
			->method('getFileUserByUuid')
			->will($this->returnValue($fileUser));
		$fileData = $this->createMock(File::class);
		$fileData
			->method('__call')
			->withConsecutive(
				[$this->equalTo('getFileId'), $this->anything()],
				[$this->equalTo('getName'), $this->anything()]
			)
			->will($this->returnValueMap([
				['getFileId', [], 1],
				['getName', [], 'Filename']
			]));
		$this->fileMapper
			->method('getById')
			->will($this->returnValue($fileData));
		$node = $this->createMock(FileNode::class);
		$this->root
			->method('getById')
			->will($this->returnValue([$node]));

		$actual = $this->controller->createToSign('uuid', 'email', 'password', 'signPassword');
		$expected = new JSONResponse([
			'message' => 'Success',
			'action' => JSActions::ACTION_SIGN,
			'filename' => 'Filename',
			'description' => null,
			'pdf' => [
				'base64' => null
			]
		], Http::STATUS_OK);
		$this->assertEquals($expected, $actual);
	}
}
