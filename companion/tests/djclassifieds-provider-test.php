<?php

declare(strict_types=1);

use VDM\Plugin\Console\JoomlaMcp\Contract\ActionInterface;
use VDM\Plugin\Console\JoomlaMcp\Contract\ModelProviderInterface;
use VDM\Plugin\Console\JoomlaMcp\Domain\ActionException;
use VDM\Plugin\Console\JoomlaMcp\Provider\DjClassifiedsActionProvider;

require __DIR__ . '/bootstrap.php';

$GLOBALS['failures'] = 0;

final class DjClassifiedsTestRuntime
{
    public static array $events = [];
}

final class DjClassifiedsTestDate
{
    /** @var list<string> */
    public array $modifiers = [];

    private DateTimeImmutable $date;

    public function __construct(string $instant, string $timezone, private readonly ?string $sqlOverride = null)
    {
        $this->date = new DateTimeImmutable($instant, new DateTimeZone($timezone));
    }

    public function modify(string $modifier): self
    {
        DjClassifiedsTestRuntime::$events[] = 'modify:' . $modifier;
        $modified = $this->date->modify($modifier);

        if (!$modified instanceof DateTimeImmutable) {
            throw new RuntimeException('The test date could not be modified.');
        }

        $this->date = $modified;
        $this->modifiers[] = $modifier;

        return $this;
    }

    public function toSQL(): string
    {
        return $this->sqlOverride ?? $this->date->format('Y-m-d H:i:s');
    }
}

final class DjClassifiedsTestFactory
{
    private static ?DjClassifiedsTestDate $dateTemplate = null;
    public static int $dateCalls = 0;
    /** @var list<DjClassifiedsTestDate> */
    public static array $dates = [];

    public static function configureDate(string $instant, string $timezone, ?string $sqlOverride = null): void
    {
        self::$dateTemplate = new DjClassifiedsTestDate($instant, $timezone, $sqlOverride);
        self::$dateCalls = 0;
        self::$dates = [];
    }

    public static function getDate(): object
    {
        if (self::$dateTemplate === null) {
            throw new RuntimeException('The test Joomla date was not configured.');
        }

        self::$dateCalls++;
        DjClassifiedsTestRuntime::$events[] = 'date';
        $date = clone self::$dateTemplate;
        self::$dates[] = $date;

        return $date;
    }

    public static function getApplication(): object
    {
        return new stdClass();
    }
}

final class DjClassifiedsTestParams
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $name, mixed $default = null): mixed
    {
        DjClassifiedsTestRuntime::$events[] = sprintf('param:%s:%s', $name, (string) $default);
        DjClassifiedsTestComponentHelper::$parameterReads[] = [$name, $default];

        return array_key_exists($name, $this->values) ? $this->values[$name] : $default;
    }
}

final class DjClassifiedsTestComponentHelper
{
    /** @var array<string, mixed> */
    public static array $values = [];
    /** @var list<string> */
    public static array $calls = [];
    /** @var list<array{string, mixed}> */
    public static array $parameterReads = [];

    public static function getParams(string $component): object
    {
        self::$calls[] = $component;
        DjClassifiedsTestRuntime::$events[] = 'params:' . $component;

        return new DjClassifiedsTestParams(self::$values);
    }
}

if (!class_exists('Joomla\\CMS\\Factory', false)) {
    class_alias(DjClassifiedsTestFactory::class, 'Joomla\\CMS\\Factory');
}
if (!class_exists('Joomla\\CMS\\Component\\ComponentHelper', false)) {
    class_alias(DjClassifiedsTestComponentHelper::class, 'Joomla\\CMS\\Component\\ComponentHelper');
}

final class DjClassifiedsTestModel
{
    /** @param array<string, mixed> $existing */
    public function __construct(public array $existing = [])
    {
    }

    public int $saveCalls = 0;
    public int $getItemCalls = 0;
    /** @var array<string, mixed> */
    public array $saved = [];

    /** @param array<string, mixed> $data */
    public function save(array $data): bool
    {
        DjClassifiedsTestRuntime::$events[] = 'save';
        $this->saveCalls++;
        $this->saved = $data;

        return true;
    }

    public function getState(string $key, int $default = 0): int
    {
        return $key === 'item.id' ? 73 : $default;
    }

    public function getItem(int $id): object
    {
        DjClassifiedsTestRuntime::$events[] = 'getItem';
        $this->getItemCalls++;
        $source = $this->saveCalls > 0 ? $this->saved : $this->existing;

        return (object) array_merge(['id' => $id], $source);
    }
}

function resetDjClassifiedsRuntime(
    string $instant = '2026-03-28 12:30:00',
    string $timezone = 'Europe/Warsaw',
    array $params = [],
    ?string $sqlOverride = null,
): void {
    DjClassifiedsTestRuntime::$events = [];
    DjClassifiedsTestComponentHelper::$values = $params;
    DjClassifiedsTestComponentHelper::$calls = [];
    DjClassifiedsTestComponentHelper::$parameterReads = [];
    DjClassifiedsTestFactory::configureDate($instant, $timezone, $sqlOverride);
}

function djClassifiedsItemAction(string $operation, DjClassifiedsTestModel $model): ActionInterface
{
    $models = new class ($model) implements ModelProviderInterface {
        public function __construct(private DjClassifiedsTestModel $model)
        {
        }

        public function administrator(string $component, string $modelName, ?string $legacyModelPrefix = null): object
        {
            expect($component === 'com_djclassifieds', 'DJ-Classifieds provider selected an unexpected component.');
            expect($modelName === 'Item', 'DJ-Classifieds item action selected an unexpected model.');
            expect($legacyModelPrefix === 'DjClassifiedsModel', 'DJ-Classifieds legacy model prefix changed.');

            return $this->model;
        }
    };
    $provider = new DjClassifiedsActionProvider(new stdClass(), $models);
    $name = 'djclassifieds.items.' . $operation;

    foreach ($provider->entityActions() as $action) {
        if ($action->descriptor()->name === $name) {
            return $action;
        }
    }

    throw new RuntimeException(sprintf('Provider did not register %s.', $name));
}

function applyDjClassifiedsItemWrite(string $operation, DjClassifiedsTestModel $model, array $data): array
{
    $input = [
        'data' => $data,
        'dryRun' => false,
        '_edgeConfirmed' => true,
    ];

    if ($operation === 'update') {
        $input = ['id' => 55] + $input;
    }

    return djClassifiedsItemAction($operation, $model)->execute($input);
}

test('DJ-Classifieds provider owns inspect and all entity action descriptors', static function (): void {
    $models = new class implements ModelProviderInterface {
        public function administrator(string $component, string $modelName, ?string $legacyModelPrefix = null): object
        {
            return new stdClass();
        }
    };
    $provider = new DjClassifiedsActionProvider(new stdClass(), $models);
    $inspection = $provider->inspectionAction();
    $entityActions = $provider->entityActions();
    $names = array_map(static fn (ActionInterface $action): string => $action->descriptor()->name, $entityActions);

    expect($inspection->descriptor()->name === 'djclassifieds.inspect', 'Provider does not own the inspect action.');
    expect(count($entityActions) === 35, 'Provider did not register all 35 DJ-Classifieds entity actions.');
    expect(count($names) === count(array_unique($names)), 'Provider registered duplicate DJ-Classifieds entity action names.');
    expect(in_array('djclassifieds.items.create', $names, true), 'Provider omitted the item create action.');
    expect(in_array('djclassifieds.items.state', $names, true), 'Provider omitted the item state action.');
    expect(!in_array('djclassifieds.profiles.state', $names, true), 'Provider added an unsupported profiles state action.');
});

test('CoreEntityAction contains no DJ-Classifieds-specific literal or expiry helper', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/plugin/src/Action/CoreEntityAction.php');
    expect(is_string($source), 'Could not read CoreEntityAction source.');

    foreach (['djclassifieds', 'DJ-Classifieds', 'exp_days', 'date_exp', 'ComponentHelper', 'deriveJoinedModelFields'] as $specific) {
        expect(!str_contains($source, $specific), sprintf('CoreEntityAction still contains provider-specific token "%s".', $specific));
    }
});

test('dry-run and missing confirmation do not execute the provider expiry hook', static function (): void {
    resetDjClassifiedsRuntime(params: ['exp_days' => '3']);
    $model = new DjClassifiedsTestModel();
    $action = djClassifiedsItemAction('create', $model);
    $preview = $action->execute(['data' => ['name' => 'Preview']]);
    expect($preview['dryRun'] === true && $preview['applied'] === false, 'Item create did not remain preview-first.');
    expect($model->saveCalls === 0, 'Item create dry-run invoked the Joomla model.');

    try {
        $action->execute(['data' => ['name' => 'Unconfirmed'], 'dryRun' => false]);
        expect(false, 'Unconfirmed item create was applied.');
    } catch (ActionException $exception) {
        expect($exception->errorCode === 'CONFIRMATION_REQUIRED', 'Unconfirmed item create returned a different error.');
    }

    expect(DjClassifiedsTestComponentHelper::$calls === [], 'The hook read component parameters before confirmation.');
    expect(DjClassifiedsTestFactory::$dateCalls === 0, 'The hook read Joomla time before confirmation.');
    expect($model->saveCalls === 0, 'An unconfirmed item create reached model save.');
});

test('create with absent exp_days uses the component default and derives in Factory timezone before save', static function (): void {
    resetDjClassifiedsRuntime();
    $model = new DjClassifiedsTestModel();
    applyDjClassifiedsItemWrite('create', $model, ['name' => 'New listing']);

    expect($model->saved['exp_days'] === 7, 'Absent exp_days did not use the component parameter default of seven days.');
    expect($model->saved['date_exp'] === '2026-04-04 12:30:00', 'Expiry date did not preserve the Joomla Factory timezone across the DST boundary.');
    expect(DjClassifiedsTestComponentHelper::$calls === ['com_djclassifieds'], 'The hook queried parameters for a different component.');
    expect(DjClassifiedsTestComponentHelper::$parameterReads === [['exp_days', '7']], 'The exp_days component parameter/default changed.');
    expect(DjClassifiedsTestFactory::$dateCalls === 1, 'The configured Joomla date provider was not used exactly once.');
    expect(DjClassifiedsTestFactory::$dates[0]->modifiers === ['+7 day'], 'The expiry modifier changed.');
    expect(DjClassifiedsTestRuntime::$events === [
        'params:com_djclassifieds', 'param:exp_days:7', 'date', 'modify:+7 day', 'save', 'getItem',
    ], 'Expiry derivation did not happen after confirmation and before model save/binding.');
});

test('create distinguishes null, empty, numeric zero, and string zero exp_days', static function (): void {
    foreach ([null, ''] as $value) {
        resetDjClassifiedsRuntime(params: ['exp_days' => '5']);
        $model = new DjClassifiedsTestModel();
        applyDjClassifiedsItemWrite('create', $model, ['name' => 'No expiry derivation', 'exp_days' => $value]);
        expect(array_key_exists('exp_days', $model->saved) && $model->saved['exp_days'] === $value, 'Null/empty exp_days was not passed through unchanged.');
        expect(!array_key_exists('date_exp', $model->saved), 'Null/empty exp_days unexpectedly derived date_exp.');
        expect(DjClassifiedsTestComponentHelper::$calls === [], 'Explicit null/empty exp_days incorrectly read component defaults.');
        expect(DjClassifiedsTestFactory::$dateCalls === 0, 'Explicit null/empty exp_days incorrectly read Joomla time.');
    }

    foreach ([0, '0'] as $value) {
        resetDjClassifiedsRuntime();
        $model = new DjClassifiedsTestModel();
        applyDjClassifiedsItemWrite('create', $model, ['name' => 'Unlimited listing', 'exp_days' => $value]);
        expect($model->saved['exp_days'] === 0, 'Zero exp_days was not normalized to integer zero.');
        expect($model->saved['date_exp'] === '2038-01-01 00:00:00', 'Zero exp_days did not retain the 2038 sentinel.');
        expect(DjClassifiedsTestComponentHelper::$calls === [] && DjClassifiedsTestFactory::$dateCalls === 0, 'Zero exp_days unexpectedly loaded parameters or current time.');
    }
});

test('update preserves absent/null/empty/unchanged expiry and recalculates changed positive days', static function (): void {
    $existing = ['name' => 'Old', 'exp_days' => '4', 'date_exp' => '2026-02-01 12:30:00'];

    foreach ([['name' => 'Renamed'], ['exp_days' => null], ['exp_days' => ''], ['exp_days' => '4']] as $data) {
        resetDjClassifiedsRuntime(params: ['exp_days' => '9']);
        $model = new DjClassifiedsTestModel($existing);
        applyDjClassifiedsItemWrite('update', $model, $data);
        expect($model->saved['date_exp'] === $existing['date_exp'], 'An absent/null/empty/unchanged expiry altered the existing date_exp.');
        expect($model->saved['exp_days'] === '4', 'An absent/null/empty/unchanged expiry did not preserve the existing exp_days value.');
        expect(DjClassifiedsTestComponentHelper::$calls === [], 'An update unexpectedly loaded create defaults.');
        expect(DjClassifiedsTestFactory::$dateCalls === 0, 'An unchanged update unexpectedly recalculated the expiry date.');
    }

    resetDjClassifiedsRuntime();
    $model = new DjClassifiedsTestModel($existing);
    applyDjClassifiedsItemWrite('update', $model, ['exp_days' => 2]);
    expect($model->saved['exp_days'] === 2, 'Changed update exp_days was not normalized to integer.');
    expect($model->saved['date_exp'] === '2026-03-30 12:30:00', 'Changed update exp_days did not derive a date in the configured timezone.');
    expect(DjClassifiedsTestRuntime::$events === ['getItem', 'date', 'modify:+2 day', 'save', 'getItem'], 'Update hook order changed relative to existing-row read and model save/binding.');
});

test('update retains the legacy zero-row repair and empty-existing fallback behavior', static function (): void {
    resetDjClassifiedsRuntime();
    $model = new DjClassifiedsTestModel(['name' => 'Old', 'exp_days' => '0', 'date_exp' => '2040-01-01 00:00:00']);
    applyDjClassifiedsItemWrite('update', $model, ['exp_days' => 0]);
    expect($model->saved['exp_days'] === 0 && $model->saved['date_exp'] === '2038-01-01 00:00:00', 'Legacy zero row with non-sentinel date was not repaired.');

    resetDjClassifiedsRuntime();
    $model = new DjClassifiedsTestModel(['name' => 'Old', 'exp_days' => '0', 'date_exp' => '2038-01-01 00:00:00']);
    applyDjClassifiedsItemWrite('update', $model, ['exp_days' => '0']);
    expect($model->saved['exp_days'] === '0' && $model->saved['date_exp'] === '2038-01-01 00:00:00', 'Already-correct zero sentinel row was not preserved.');
    expect(DjClassifiedsTestFactory::$dateCalls === 0, 'Already-correct zero sentinel row unnecessarily loaded Joomla time.');

    resetDjClassifiedsRuntime(params: ['exp_days' => '3']);
    $model = new DjClassifiedsTestModel(['id' => 55]);
    applyDjClassifiedsItemWrite('update', $model, ['name' => 'No writable fields were returned']);
    expect($model->saved['exp_days'] === 3, 'An empty existing write-data set no longer follows the current default path.');
    expect($model->saved['date_exp'] === '2026-03-31 12:30:00', 'Empty-existing fallback did not derive the configured date.');
    expect(DjClassifiedsTestRuntime::$events === [
        'getItem', 'params:com_djclassifieds', 'param:exp_days:7', 'date', 'modify:+3 day', 'save', 'getItem',
    ], 'Empty-existing update order changed.');
});

test('expiry retains the 2038 cap and the existing Joomla Date sentinel check', static function (): void {
    resetDjClassifiedsRuntime('2038-01-02 00:00:00', 'UTC');
    $model = new DjClassifiedsTestModel();
    applyDjClassifiedsItemWrite('create', $model, ['name' => 'Capped', 'exp_days' => 1]);
    expect($model->saved['date_exp'] === '2038-01-01 00:00:00', 'Expiry beyond the cap was not limited to the 2038 sentinel.');

    resetDjClassifiedsRuntime('2026-01-01 00:00:00', 'UTC', sqlOverride: '1970-01-01 1:00:00');
    $model = new DjClassifiedsTestModel();
    applyDjClassifiedsItemWrite('create', $model, ['name' => 'Date sentinel', 'exp_days' => 1]);
    expect($model->saved['date_exp'] === '2038-01-01 00:00:00', 'The existing Joomla Date sentinel was not mapped to the unlimited-expiry value.');
});

test('non-item DJ-Classifieds actions do not receive the item expiry hook', static function (): void {
    resetDjClassifiedsRuntime(params: ['exp_days' => '4']);
    $model = new DjClassifiedsTestModel();
    $models = new class ($model) implements ModelProviderInterface {
        public function __construct(private DjClassifiedsTestModel $model)
        {
        }

        public function administrator(string $component, string $modelName, ?string $legacyModelPrefix = null): object
        {
            return $this->model;
        }
    };
    $provider = new DjClassifiedsActionProvider(new stdClass(), $models);
    $action = null;

    foreach ($provider->entityActions() as $candidate) {
        if ($candidate->descriptor()->name === 'djclassifieds.categories.create') {
            $action = $candidate;
            break;
        }
    }

    expect($action instanceof ActionInterface, 'Provider omitted DJ-Classifieds category create.');
    $action->execute(['data' => ['name' => 'Category'], 'dryRun' => false, '_edgeConfirmed' => true]);
    expect(!array_key_exists('exp_days', $model->saved) && !array_key_exists('date_exp', $model->saved), 'Item expiry fields leaked into a non-item action.');
    expect(DjClassifiedsTestComponentHelper::$calls === [] && DjClassifiedsTestFactory::$dateCalls === 0, 'A non-item action invoked the item expiry hook.');
});

if ($GLOBALS['failures'] > 0) {
    file_put_contents('php://stderr', sprintf("%d DJ-Classifieds provider regression test(s) failed.\n", $GLOBALS['failures']), FILE_APPEND);
    exit(1);
}

file_put_contents('php://stdout', "All DJ-Classifieds provider regression tests passed.\n", FILE_APPEND);
