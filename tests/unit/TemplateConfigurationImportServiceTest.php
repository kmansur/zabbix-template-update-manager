<?php

final class CMessageHelper {
	public const MESSAGE_TYPE_ERROR = 'error';
	public static array $messages = [];

	public static function getMessages(): array {
		return self::$messages;
	}
}

final class ZtumApiTestConfiguration {
	public bool $result = true;
	public string $message = '';

	public function import(array $params): bool {
		if (!$this->result && $this->message !== '') {
			CMessageHelper::$messages[] = [
				'type' => CMessageHelper::MESSAGE_TYPE_ERROR,
				'message' => $this->message
			];
		}
		return $this->result;
	}
}

final class API {
	public static $wrapper = null;
	public static ZtumApiTestConfiguration $configuration;

	public static function getWrapper() {
		return self::$wrapper;
	}

	public static function setWrapper($wrapper = null): void {
		self::$wrapper = $wrapper;
	}

	public static function Configuration(): ZtumApiTestConfiguration {
		return self::$configuration;
	}
}

final class ZtumApiTestClient {
	public object $response;
	public ?\Throwable $throwable = null;
	public array $lastCall = [];

	public function callMethod(string $api, string $method, array $params, array $auth): object {
		$this->lastCall = compact('api', 'method', 'params', 'auth');
		if ($this->throwable !== null) {
			throw $this->throwable;
		}
		return $this->response;
	}
}

final class ZtumApiTestWrapper {
	public array $auth = ['type' => 0, 'auth' => 'test-token'];

	public function __construct(private ZtumApiTestClient $client) {
	}

	public function getClient(): ZtumApiTestClient {
		return $this->client;
	}
}

require_once dirname(__DIR__, 2).'/src/Service/TemplateConfigurationImportService.php';

use Modules\ZabbixTemplateUpdateManager\Exception\ConfigurationImportException;
use Modules\ZabbixTemplateUpdateManager\Service\TemplateConfigurationImportService;

function assertImportBehavior(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, $message."\n");
		exit(1);
	}
}

API::$configuration = new ZtumApiTestConfiguration();
$service = new TemplateConfigurationImportService();

$client = new ZtumApiTestClient();
$client->response = (object) ['errorCode' => 0, 'errorMessage' => '', 'data' => true];
$wrapper = new ZtumApiTestWrapper($client);
API::$wrapper = $wrapper;

$service->import('{"zabbix_export":{}}', 'json');

assertImportBehavior(API::$wrapper === $wrapper, 'The frontend API wrapper must be restored after a successful direct client call.');
assertImportBehavior(($client->lastCall['api'] ?? null) === 'configuration', 'The direct client call must target the configuration API.');
assertImportBehavior(($client->lastCall['method'] ?? null) === 'import', 'The direct client call must target configuration.import.');
assertImportBehavior(($client->lastCall['auth'] ?? null) === $wrapper->auth, 'The direct client call must preserve frontend authentication.');

$client->response = (object) ['errorCode' => -32602, 'errorMessage' => 'Native Zabbix import detail', 'data' => null];
try {
	$service->import('{"zabbix_export":{}}', 'json');
	assertImportBehavior(false, 'A native API error response must throw ConfigurationImportException.');
}
catch (ConfigurationImportException $exception) {
	assertImportBehavior(
		$exception->getMachineCode() === 'configuration_import_failed'
			&& str_contains($exception->getMessage(), 'Native Zabbix import detail'),
		'The typed import exception must preserve the native API error detail and machine code.'
	);
}
assertImportBehavior(API::$wrapper === $wrapper, 'The frontend API wrapper must be restored after a failed direct client call.');

$client->throwable = new RuntimeException('client exploded');
try {
	$service->import('{"zabbix_export":{}}', 'json');
	assertImportBehavior(false, 'A native API client exception must be wrapped.');
}
catch (ConfigurationImportException $exception) {
	assertImportBehavior(
		$exception->getPrevious() instanceof RuntimeException
			&& str_contains($exception->getMessage(), 'client exploded'),
		'The import exception must preserve the API client exception as previous diagnostic evidence.'
	);
}
$client->throwable = null;

API::$wrapper = null;
API::$configuration->result = false;
API::$configuration->message = 'Fallback frontend import detail';
CMessageHelper::$messages = [['type' => 'info', 'message' => 'pre-existing']];

try {
	$service->import('{"zabbix_export":{}}', 'json');
	assertImportBehavior(false, 'A false fallback import result must throw.');
}
catch (ConfigurationImportException $exception) {
	assertImportBehavior(
		str_contains($exception->getMessage(), 'Fallback frontend import detail'),
		'The compatibility fallback must retain only current-call frontend error detail.'
	);
}

echo "TemplateConfigurationImportService behavioral tests passed.\n";
