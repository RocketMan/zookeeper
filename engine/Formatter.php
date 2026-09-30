<?php

namespace ZK\Engine;

class Formatter {
    public function __construct(
        protected Request $request,
    ) {}

    private function hourToLocale(string $hour, bool $full = false): string {
        // account for legacy, free-format time encoding
        if(!is_numeric($hour) || !$this->request->isUsLocale())
            return $hour;

        $h = (int)floor($hour/100);
        $m = (int)$hour % 100;
        $min = $m || $full ? (':' . sprintf("%02d", $m)) : '';
    
        switch($h) {
        case 0:
            return $m ? ("12" . $min . "am") : "midnight";
        case 12:
            return $m ? ($h . $min . "pm") : "noon";
        default:
            if ($h < 12)
                return $h . $min . "am";
            else
                return ($h - 12) . $min . "pm";
        }
    }

    public function timeToLocale(string $time): string {
        if (strlen($time) == 9 && $time[4] == '-') {
            return implode(' - ',
                array_map(
                    fn($time) => $this->hourToLocale($time),
                    explode('-', $time)
                )
            );
        } else
            return strtolower(htmlentities($time));
    }

    public function timestampToDate(?string $time): string {
        if ($time == null || $time == '') {
            return '';
        } else {
            $dateSpec = $this->request->isUsLocale() ? 'D M d, Y ' : 'D d M Y ';
            return date($dateSpec, strtotime($time));
        }
    }

    public function makeShowDateAndTime(array $row): string {
        return $this->timestampToDate($row['showdate']) . " " .
               $this->timeToLocale($row['showtime']);
    }

    public function makeShowTime(array $row): string {
        return $this->timeToLocale($row['showtime']);
    }
}
