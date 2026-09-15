<?php

namespace Modules\ZabbixTemplateUpdateManager\Repository;

use API;

final class TemplateRepository {

	private const OUTPUT_FIELDS = [
		'templateid',
		'host',
		'name',
		'uuid',
		'vendor_name',
		'vendor_version'
	];

	public static function queryOptions(): array {
		return [
			'output' => self::OUTPUT_FIELDS,
			'selectHosts' => 'count',
			'selectTemplateGroups' => ['groupid', 'name'],
			'sortfield' => 'name',
			'sortorder' => 'ASC'
		];
	}

	public function findAll(): array {
		return API::Template()->get(self::queryOptions());
	}
}
