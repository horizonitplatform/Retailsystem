<?php

namespace App\Helpers;

use Jenssegers\Date\Date as DateFormatter;

DateFormatter::setLocale('th');

class Date {

  public static function parseRange($start, $end) {
    $startDateFormated = DateFormatter::parse($start)->format('j');
    $startMonthFormated = DateFormatter::parse($start)->format('M');
    $startYearFormated = DateFormatter::parse($start)->format('Y');
    $endDateFormated = DateFormatter::parse($end)->format('j');
    $endMonthFormated = DateFormatter::parse($end)->format('M');
    $endYearFormated = DateFormatter::parse($end)->format('Y');

    if ($endYearFormated != $startYearFormated) {
      return $startDateFormated.' '.$startMonthFormated.' '.$startYearFormated.' - '.$endDateFormated.' '.$endMonthFormated.' '.$endYearFormated;
    } else {
      if ($startMonthFormated == $endMonthFormated) {
        return $startDateFormated.'-'.$endDateFormated.' '.$startMonthFormated.' '.$startYearFormated;
      } else {
        return $startDateFormated.' '.$startMonthFormated.' - '.$endDateFormated.' '.$endMonthFormated.' '.$endYearFormated;
      }
    }
  }

  public static function parseRangeMonth($start, $end) {
    $startMonthFormated = DateFormatter::parse($start)->format('M');
    $startYearFormated = DateFormatter::parse($start)->format('Y');
    $endMonthFormated = DateFormatter::parse($end)->format('M');
    $endYearFormated = DateFormatter::parse($end)->format('Y');

    if ($endYearFormated != $startYearFormated) {
      return $startMonthFormated.' '.$startYearFormated.' - '.$endMonthFormated.' '.$endYearFormated;
    } else {
      if ($startMonthFormated == $endMonthFormated) {
        return $startMonthFormated.' '.$startYearFormated;
      } else {
        return $startMonthFormated.' - '.$endMonthFormated.' '.$endYearFormated;
      }
    }
  }

  public static function parseMonth($start) {
    $startDateormated = DateFormatter::parse($start)->format('d');
    $startMonthFormated = DateFormatter::parse($start)->format('M');
    $startYearFormated = DateFormatter::parse($start)->format('Y');
    
    return  $startDateormated . ' ' . $startMonthFormated.' '.$startYearFormated;
  } 

}