<?php

namespace Tests\Unit;

use App\Services\JosaaOrCrAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class JosaaOrCrAdapterTest extends TestCase
{
    public function test_it_preserves_webform_state_during_option_discovery(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], $this->form(['round' => ['1' => '1']])),
            new Response(200, [], $this->form([
                'round' => ['1' => '1'],
                'institute_type' => ['IIT' => 'Indian Institute of Technology'],
            ])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $adapter = new JosaaOrCrAdapter(new Client(['handler' => $stack]));

        $sets = $adapter->optionSets(['round' => '1']);

        self::assertSame(['1' => '1'], $sets['round']);
        self::assertSame(
            ['IIT' => 'Indian Institute of Technology'],
            $sets['institute_type']
        );
        parse_str((string) $history[1]['request']->getBody(), $payload);
        self::assertSame(JosaaOrCrAdapter::ROUND, $payload['__EVENTTARGET']);
        self::assertSame('1', $payload[JosaaOrCrAdapter::ROUND]);
        self::assertSame('state-1', $payload['__VIEWSTATE']);
    }

    public function test_it_runs_the_postback_chain_and_extracts_cutoff_rows(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->form(['round' => ['1' => '1']])),
            new Response(200, [], $this->form(['institute_type' => ['IIT' => 'IIT']])),
            new Response(200, [], $this->form(['institute' => ['123' => 'Example IIT']])),
            new Response(200, [], $this->form(['program' => ['CSE' => 'Computer Science']])),
            new Response(200, [], $this->form(['seat_type' => ['OPEN' => 'OPEN']])),
            new Response(200, [], $this->resultHtml()),
        ]);
        $adapter = new JosaaOrCrAdapter(new Client([
            'handler' => HandlerStack::create($mock),
        ]));

        $rows = $adapter->fetchRows([
            'round' => '1',
            'institute_type' => 'IIT',
            'institute' => '123',
            'program' => 'CSE',
            'seat_type' => 'OPEN',
        ]);

        self::assertCount(1, $rows);
        self::assertSame('Example IIT', $rows[0]['institute']);
        self::assertSame('123', $rows[0]['opening_rank']);
        self::assertSame('456', $rows[0]['closing_rank']);
    }

    private function form(array $options): string
    {
        $fields = [
            'round' => JosaaOrCrAdapter::ROUND,
            'institute_type' => JosaaOrCrAdapter::INSTITUTE_TYPE,
            'institute' => JosaaOrCrAdapter::INSTITUTE,
            'program' => JosaaOrCrAdapter::PROGRAM,
            'seat_type' => JosaaOrCrAdapter::SEAT_TYPE,
        ];
        $selects = '';
        foreach ($options as $key => $items) {
            $selects .= '<select name="'.$fields[$key].'"><option value="0">--Select--</option>';
            foreach ($items as $value => $label) {
                $selects .= '<option value="'.$value.'">'.$label.'</option>';
            }
            $selects .= '</select>';
        }

        return '<html><form><input type="hidden" name="__VIEWSTATE" value="state-1">'
            .$selects.'</form></html>';
    }

    private function resultHtml(): string
    {
        return '<html><div id="ctl00_ContentPlaceHolder1_pnlDisplayDetails"><table>'
            .'<tr><th>Institute</th><th>Academic Program</th><th>Opening Rank</th><th>Closing Rank</th></tr>'
            .'<tr><td>Example IIT</td><td>Computer Science</td><td>123</td><td>456</td></tr>'
            .'</table></div></html>';
    }
}
