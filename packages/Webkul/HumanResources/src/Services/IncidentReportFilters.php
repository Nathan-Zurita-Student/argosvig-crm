<?php

namespace Webkul\HumanResources\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentReportFilters
{
    public const SEVERITIES = ['low', 'medium', 'high', 'critical', 'serious'];

    public static function rules(): array
    {
        return [
            'from' => ['nullable', 'prohibited_unless:section,incidents', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'prohibited_unless:section,incidents', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'severity' => ['nullable', 'prohibited_unless:section,incidents', Rule::in(self::SEVERITIES)],
            'location' => ['nullable', 'prohibited_unless:section,incidents', 'string', 'max:120'],
            'order' => ['nullable', 'prohibited_unless:section,incidents', Rule::in(['location'])],
        ];
    }

    public static function fromQuestion(string $question): ?array
    {
        $question = Str::squish($question);
        $terms = Str::lower(Str::ascii($question));
        if (! preg_match('/\bpdf\b/', $terms)
            || ! preg_match('/\b(?:(?:pdf|relatorio|exportacao|lista|listagem) (?:com |d(?:e|as|os) |sobre )?|(?:gere|exporte|baixe|listar|exportar) (?:as )?)(?:todas? as )?ocorrencias\b/', $terms)
            || preg_match('/\b(?:csv|xlsx|excel|planilha|modelo|exemplo)\b/', $terms)) {
            return null;
        }

        $filters = ['section' => 'incidents'];
        [$filters['from'], $filters['to']] = self::period($terms);
        $filters['severity'] = match (true) {
            preg_match('/\b(?:criticas? (?:e|ou) altas?|altas? (?:e|ou) criticas?|graves?)\b/', $terms) === 1 => 'serious',
            preg_match('/\bcriticas?\b/', $terms) === 1 => 'critical',
            preg_match('/\b(?:altas? ocorrencias|ocorrencias (?:de gravidade )?altas?|gravidade alta|severidade alta)\b/', $terms) === 1 => 'high',
            preg_match('/\b(?:ocorrencias (?:de gravidade )?medias?|gravidade media|severidade media)\b/', $terms) === 1 => 'medium',
            preg_match('/\b(?:ocorrencias (?:de gravidade )?baixas?|gravidade baixa|severidade baixa)\b/', $terms) === 1 => 'low',
            default => null,
        };

        if (preg_match('/\b(?:no|do) (?:posto|local) (?:["“]([^"”]+)["”]|(.+?)(?=\s+(?:nos? [úu]ltimos?|d[eo]s? [úu]ltimos?|deste m[eê]s|do m[eê]s passado|entre |de \d|com gravidade|em pdf|por posto|por local)|[.!?;,]|$))/iu', $question, $matches)) {
            $filters['location'] = trim($matches[1] ?: $matches[2]);
        }

        if (preg_match('/\bpor (?:posto|local)\b/', $terms)) {
            $filters['order'] = 'location';
        }

        Validator::make($filters, self::rules())->validate();

        return array_filter($filters, fn ($value) => $value !== null);
    }

    private static function period(string $terms): array
    {
        $today = CarbonImmutable::today();
        $date = '(\d{2}/\d{2}/\d{4}|\d{4}-\d{2}-\d{2})';

        if (preg_match('~\b'.$date.'\s*(?:a|ate|e)\s*'.$date.'\b~', $terms, $matches)) {
            return [self::isoDate($matches[1]), self::isoDate($matches[2])];
        }

        if (preg_match('/\bultim[oa]s?\s+(\d{1,4})\s+dias?\b/', $terms, $matches)) {
            $days = (int) $matches[1];
            if ($days >= 1 && $days <= 3660) {
                return [$today->subDays($days - 1)->toDateString(), $today->toDateString()];
            }
        }

        if (preg_match('/\bmes passado\b/', $terms)) {
            $previous = $today->subMonthNoOverflow();

            return [$previous->startOfMonth()->toDateString(), $previous->endOfMonth()->toDateString()];
        }

        if (preg_match('/\b(?:este|deste|neste) mes\b/', $terms)) {
            return [$today->startOfMonth()->toDateString(), $today->toDateString()];
        }

        if (preg_match('~\b(?:ultim[oa]s?|periodo|desde|'.$date.')\b~', $terms)) {
            throw ValidationException::withMessages(['question' => trans('human_resources::reports.invalid_period')]);
        }

        return [null, null];
    }

    private static function isoDate(string $date): string
    {
        return implode('-', array_reverse(explode('/', $date)));
    }

    public static function apply(Builder $query, array $filters): void
    {
        if (isset($filters['from'], $filters['to'])) {
            $query->where('occurred_at', '>=', $filters['from'].' 00:00:00')
                ->where('occurred_at', '<', CarbonImmutable::parse($filters['to'])->addDay()->toDateTimeString());
        }

        if (isset($filters['severity'])) {
            $query->whereIn('severity', $filters['severity'] === 'serious' ? ['high', 'critical'] : [$filters['severity']]);
        }

        if (isset($filters['location'])) {
            $like = strtr(Str::lower($filters['location']), ['!' => '!!', '%' => '!%', '_' => '!_']);
            $query->whereRaw("LOWER(location) LIKE ? ESCAPE '!'", ['%'.$like.'%']);
        }

        if (isset($filters['order'])) {
            $query->orderBy('location')->orderByDesc('occurred_at');
        }
    }

    public static function describe(array $filters): array
    {
        $labels = [];

        if (isset($filters['from'], $filters['to'])) {
            $labels[] = trans('human_resources::reports.period', [
                'from' => CarbonImmutable::parse($filters['from'])->format('d/m/Y'),
                'to' => CarbonImmutable::parse($filters['to'])->format('d/m/Y'),
            ]);
        }

        if (isset($filters['severity'])) {
            $labels[] = trans('human_resources::reports.severity_filter', [
                'severity' => trans('human_resources::reports.values.'.$filters['severity']),
            ]);
        }

        if (isset($filters['location'])) {
            $labels[] = trans('human_resources::reports.location_filter', ['location' => $filters['location']]);
        }

        if (isset($filters['order'])) {
            $labels[] = trans('human_resources::reports.location_order');
        }

        return $labels;
    }
}
