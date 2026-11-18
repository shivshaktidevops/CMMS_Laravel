<?php
namespace App\Support;

class LegacyResponse
{
    private const JSON_KEYS = ['details','reference_values','columns','readings','photos','user_boxes','assignments','monitoring'];
    private const BOOL_KEYS = ['active','is_active','hourly_entry','load_monitoring','is_dynamic','general_shift_only','is_unlocked','is_multi_assign'];

    public static function normalize($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (in_array((string)$k, self::JSON_KEYS, true) && is_string($v)) {
                    $decoded = json_decode($v, true);
                    $v = json_last_error() === JSON_ERROR_NONE ? $decoded : $v;
                }
                if (in_array((string)$k, self::BOOL_KEYS, true) && $v !== null) $v = (bool)$v;
                $out[$k] = self::normalize($v);
            }
            return $out;
        }
        if (is_object($value)) return self::normalize((array)$value);
        return $value;
    }
}
