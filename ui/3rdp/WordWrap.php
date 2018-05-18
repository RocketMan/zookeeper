<?php
/**
 * @author Dominic J. Eidson
 * @copyright Copyright (C) 1999 Dominic J. Eidson
 */

class WordWrap {
    /* word_wrap($string, $cols, $prefix)
     *
     * Takes $string, and wraps it on a per-word boundary (does not clip
     * words UNLESS the word is more than $cols long), no more than $cols per
     * line. Allows for optional prefix string for each line. (Was written to
     * easily format replies to e-mails, prefixing each line with "> ".
     *
     * Copyright 1999 Dominic J. Eidson, use as you wish, but give credit
     * where credit due.
     */
    public function word_wrap($string, $cols = 72, $prefix = "") {
        $t_lines = split(is_int(strpos($string, "\r"))?"\r\n":"\n", $string);
        $outlines = "";
    
        while(list(, $thisline) = each($t_lines)) {
            if(strlen($thisline) > $cols) {
                $newline = "";
                $t_l_lines = split(" ", $thisline);
    
                while(list(, $thisword) = each($t_l_lines)) {
                    while((strlen($thisword) + strlen($prefix)) > $cols) {
                        $cur_pos = 0;
                        $outlines .= $prefix;
    
                        for($num=0; $num < $cols-1; $num++) {
                            $outlines .= $thisword[$num];
                            $cur_pos++;
                        }
    
                        $outlines .= "\n";
                        $thisword = substr($thisword, $cur_pos, (strlen($thisword)-$cur_pos));
                    }
    
                    if((strlen($newline) + strlen($thisword)) > $cols) {
                        $outlines .= $prefix.$newline."\n";
                        $newline = $thisword." ";
                    } else {
                        $newline .= $thisword." ";
                    }
                }
    
                $outlines .= $prefix.$newline."\n";
            } else {
                $outlines .= $prefix.$thisline."\n";
            }
        }
        return $outlines;
    }
}
