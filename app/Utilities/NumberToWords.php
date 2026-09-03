<?php

namespace App\Utilities;
 
class NumberToWords
{
    public static function convert($amount)
    {
        $amount = (float) $amount;
        $num = number_format($amount, 2, '.', '');
        $parts = explode('.', $num);
        $whole = (int) $parts[0];
        $dec = (int) $parts[1];

        $words = self::convertWholeNumber($whole);

        if ($dec > 0) {
            $words .= " and " . self::convertWholeNumber($dec) . " Cents";
        }

        return trim($words) . " Only";
    }

    private static function convertWholeNumber($number)
    {
        if (($number < 0) || ($number > 999999999)) {
            return "$number";
        }

        $Gn = floor($number / 1000000);
        /* Millions (giga) */
        $number -= $Gn * 1000000;
        $kn = floor($number / 1000);
        /* Thousands (kilo) */
        $number -= $kn * 1000;
        $Hn = floor($number / 100);
        /* Hundreds (hecto) */
        $number -= $Hn * 100;
        $Dn = floor($number / 10);
        /* Tens (deca) */
        $n = $number % 10;
        /* Ones */

        $res = "";

        if ($Gn) {
            $res .= self::convertWholeNumber($Gn) . " Million";
        }

        if ($kn) {
            $res .= (empty($res) ? "" : " ") . self::convertWholeNumber($kn) . " Thousand";
        }

        if ($Hn) {
            $res .= (empty($res) ? "" : " ") . self::convertWholeNumber($Hn) . " Hundred";
        }

        $ones = array(
            "",
            "One",
            "Two",
            "Three",
            "Four",
            "Five",
            "Six",
            "Seven",
            "Eight",
            "Nine",
            "Ten",
            "Eleven",
            "Twelve",
            "Thirteen",
            "Fourteen",
            "Fifteen",
            "Sixteen",
            "Seventeen",
            "Eighteen",
            "Nineteen"
        );
        $tens = array(
            "",
            "",
            "Twenty",
            "Thirty",
            "Forty",
            "Fifty",
            "Sixty",
            "Seventy",
            "Eighty",
            "Ninety"
        );

        if ($Dn || $n) {
            if (!empty($res)) {
                $res .= " and ";
            }

            if ($Dn < 2) {
                $res .= $ones[$Dn * 10 + $n];
            } else {
                $res .= $tens[$Dn];
                if ($n) {
                    $res .= "-" . $ones[$n];
                }
            }
        }

        if (empty($res)) {
            $res = "Zero";
        }

        return $res;
    }
}
