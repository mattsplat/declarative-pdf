<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Interactive\FieldActions;
use Pdf\Interactive\Js;
use PHPUnit\Framework\TestCase;

final class JsTest extends TestCase
{
    public function test_simple_calculate_recipes_wrap_the_field_list(): void
    {
        self::assertSame(
            'AFSimple_Calculate("SUM", new Array("qty", "price"));',
            Js::sum('qty', 'price')->source,
        );
        self::assertSame(
            'AFSimple_Calculate("PRD", new Array("a", "b"));',
            Js::product('a', 'b')->source,
        );
        self::assertSame(
            'AFSimple_Calculate("AVG", new Array("x"));',
            Js::average('x')->source,
        );
    }

    public function test_currency_format_carries_a_matching_keystroke_filter(): void
    {
        $js = Js::formatCurrency(2, '$');

        self::assertSame('AFNumber_Format(2, 0, 0, 0, "$", true);', $js->source);
        self::assertSame('AFNumber_Keystroke(2, 0, 0, 0, "$", true);', $js->keystrokeSource);
    }

    public function test_percent_and_plain_number_recipes(): void
    {
        self::assertSame('AFPercent_Format(1, 0);', Js::formatPercent()->source);
        self::assertSame('AFNumber_Format(0, 0, 0, 0, "", true);', Js::formatNumber(0)->source);
    }

    public function test_validate_range_builds_a_bounded_check_with_a_message(): void
    {
        $js = Js::validateRange(0.0, 100.0);

        self::assertStringContainsString('parseFloat(event.value)', $js->source);
        self::assertStringContainsString('v < 0 || v > 100', $js->source);
        self::assertStringContainsString('event.rc = false', $js->source);
        self::assertStringContainsString('between 0 and 100', $js->source);
    }

    public function test_validate_range_with_one_bound_only(): void
    {
        self::assertStringContainsString('v < 5', Js::validateRange(5.0, null)->source);
        self::assertStringNotContainsString('v > ', Js::validateRange(5.0, null)->source);
        self::assertStringContainsString('at most 10', Js::validateRange(null, 10.0)->source);
    }

    public function test_raw_is_passed_through_verbatim(): void
    {
        self::assertSame('event.value = 1;', Js::raw('event.value = 1;')->source);
    }

    public function test_string_arguments_are_escaped(): void
    {
        self::assertStringContainsString('\\"Total\\"', Js::validateRange(null, null, 'Enter "Total"')->source);
    }

    public function test_field_actions_report_calculate_and_map_to_aa_keys(): void
    {
        $actions = new FieldActions(
            keystroke: Js::raw('k'),
            format: Js::raw('f'),
            validate: Js::raw('v'),
            calculate: Js::raw('c'),
        );

        self::assertTrue($actions->hasCalculate());
        self::assertFalse($actions->isEmpty());
        self::assertSame(['K' => 'k', 'F' => 'f', 'V' => 'v', 'C' => 'c'], $actions->additionalActions());
    }

    public function test_empty_field_actions(): void
    {
        $actions = new FieldActions();

        self::assertTrue($actions->isEmpty());
        self::assertFalse($actions->hasCalculate());
        self::assertSame([], $actions->additionalActions());
    }
}
