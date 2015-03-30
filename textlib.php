<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * textlib.php - This library is to process external files of different types.
 *
 * @package    plagiarism_plagiarisma
 * @subpackage plagiarism
 * @copyright  2009 Alex Rembish https://github.com/rembish/TextAtAnyCost
 * @copyright  2015 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * convert zipped xml
 * @param string $archivefile
 * @param string $contentfile
 */
function plagiarisma_convert_zippedxml($archivefile, $contentfile) {
    $zip = new ZipArchive;
    if ($zip->open($archivefile)) {
        if (($index = $zip->locateName($contentfile)) !== false) {
            $content = $zip->getFromIndex($index);
            $zip->close();
            $content = str_replace("<w:p ", "\n<w:p ", $content);
            $xml = new DOMDocument();
            $xml->loadXML($content, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
            return strip_tags($xml->saveXML());
        } else {
            echo "Not found!";
        }
        $zip->close();
    }
     return "ERROR in text Tokenization";
}
/**
 * rtf_is_plaintext()
 * @param array $s
 */
function rtf_is_plaintext($s) {
    $failat = array("*", "fonttbl", "colortbl", "datastore", "themedata");
    for ($i = 0; $i < count($failat); $i++) {
        if (!empty($s[$failat[$i]])) {
            return false;
        }
    }
    return true;
}
/**
 * function to convert rtf to plain text
 * @param string $filename
 */
function plagiarisma_rtf2txt($filename) {
    $text = file_get_contents($filename);
    if (!strlen($text)) {
        return "";
    }
    // Speeding up via cutting binary data from large rtf's.
    if (strlen($text) > 1024 * 1024) {
        $text = preg_replace("#[\r\n]#", "", $text);
        $text = preg_replace("#[0-9a-f]{128,}#is", "", $text);
    }

    // For Unicode escaping.
    $text = str_replace("\\'3f", "?", $text);
    $text = str_replace("\\'3F", "?", $text);

    $document = "";
    $stack = array();
    $j = -1;

    $fonts = array();

    for ($i = 0, $len = strlen($text); $i < $len; $i++) {
        $c = $text[$i];

        switch ($c) {
            case "\\":
                $nc = $text[$i + 1];

                if ($nc == '\\' && rtf_is_plaintext($stack[$j])) {
                    $document .= '\\';
                } else if ($nc == '~' && rtf_is_plaintext($stack[$j])) {
                    $document .= ' ';
                } else if ($nc == '_' && rtf_is_plaintext($stack[$j])) {
                    $document .= '-';
                } else if ($nc == '*') {
                    $stack[$j]["*"] = true;
                } else if ($nc == "'") {
                    $hex = substr($text, $i + 2, 2);
                    if (rtf_is_plaintext($stack[$j])) {
                        if (!empty($stack[$j]["mac"]) || @$fonts[$stack[$j]["f"]] == 77) {
                            $document .= from_macroman(hexdec($hex));
                        } else if (@$stack[$j]["ansicpg"] == "1251" || @$stack[$j]["lang"] == "1029") {
                            $document .= chr(hexdec($hex));
                        } else {
                            $document .= "&#".hexdec($hex).";";
                        }
                    }
                    $i += 2;
                } else if ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                    $word = "";
                    $param = null;

                    for ($k = $i + 1, $m = 0; $k < strlen($text); $k++, $m++) {
                        $nc = $text[$k];
                        if ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                            if (empty($param)) {
                                $word .= $nc;
                            } else {
                                break;
                            }
                        } else if ($nc >= '0' && $nc <= '9') {
                            $param .= $nc;
                        } else if ($nc == '-') {
                            if (empty($param)) {
                                $param .= $nc;
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                    $i += $m - 1;

                    $totext = "";
                    switch (strtolower($word)) {

                        case "u":
                            $totext .= html_entity_decode("&#x".sprintf("%04x", $param).";");
                            $ucdelta = !empty($stack[$j]["uc"]) ? @$stack[$j]["uc"] : 1;

                            if ($ucdelta > 0) {
                                $i += $ucdelta;
                            }
                        break;

                        case "par": case "page": case "column": case "line": case "lbr":
                            $totext .= "\n";
                        break;
                        case "emspace": case "enspace": case "qmspace":
                            $totext .= " ";
                        break;
                        case "tab":
                            $totext .= "\t";
                        break;
                        case "chdate":
                            $totext .= date("m.d.Y");
                        break;
                        case "chdpl":
                            $totext .= date("l, j F Y");
                        break;
                        case "chdpa":
                            $totext .= date("D, j M Y");
                        break;
                        case "chtime":
                            $totext .= date("H:i:s");
                        break;
                        case "emdash":
                            $totext .= html_entity_decode("&mdash;");
                        break;
                        case "endash":
                            $totext .= html_entity_decode("&ndash;");
                        break;
                        case "bullet":
                            $totext .= html_entity_decode("&#149;");
                        break;
                        case "lquote":
                            $totext .= html_entity_decode("&lsquo;");
                        break;
                        case "rquote":
                            $totext .= html_entity_decode("&rsquo;");
                        break;
                        case "ldblquote":
                            $totext .= html_entity_decode("&laquo;");
                        break;
                        case "rdblquote":
                            $totext .= html_entity_decode("&raquo;");
                        break;

                        case "bin":
                            $i += $param;
                        break;

                        case "fcharset":
                            $fonts[@$stack[$j]["f"]] = $param;
                        break;

                        default:
                            $stack[$j][strtolower($word)] = empty($param) ? true : $param;
                        break;
                    }
                    if (rtf_is_plaintext($stack[$j])) {
                        $document .= $totext;
                    }
                } else {
                      $document .= " ";
                }
                $i++;
            break;

            case "{":
                if ($j == -1) {
                    $stack[++$j] = array();
                } else {
                    array_push($stack, $stack[$j++]);
                }
            break;

            case "}":
                array_pop($stack);
                $j--;
            break;

            case "\0": case "\r": case "\f": case "\b": case "\t":
            break;

            case "\n":
                $document .= " ";
            break;

            default:
                if (rtf_is_plaintext($stack[$j])) {
                    $document .= $c;
                }
            break;
        }
    }
    return html_entity_decode($document);
}
/**
 * decode_ascii_hex()
 * @param string $input
 */
function decode_ascii_hex($input) {
    $output = "";

    $isodd = true;
    $iscomment = false;

    for ($i = 0, $codehigh = -1; $i < strlen($input) && $input[$i] != '>'; $i++) {
        $c = $input[$i];

        if ($iscomment) {
            if ($c == '\r' || $c == '\n') {
                $iscomment = false;
            }
            continue;
        }

        switch($c) {
            case '\0': case '\t': case '\r': case '\f': case '\n': case ' ':
            break;
            case '%':
                $iscomment = true;
            break;

            default:
                $code = hexdec($c);
                if ($code === 0 && $c != '0') {
                    return "";
                }
                if ($isodd) {
                    $codehigh = $code;
                } else {
                    $output .= chr($codehigh * 16 + $code);
                }
                $isodd = !$isodd;
            break;
        }
    }

    if ($input[$i] != '>') {
        return "";
    }
    if ($isodd) {
        $output .= chr($codehigh * 16);
    }
    return $output;
}
/**
 * decode_ascii85()
 * @param string $input
 */
function decode_ascii85($input) {
    $output = "";

    $iscomment = false;
    $ords = array();

    for ($i = 0, $state = 0; $i < strlen($input) && $input[$i] != '~'; $i++) {
        $c = $input[$i];

        if ($iscomment) {
            if ($c == '\r' || $c == '\n') {
                $iscomment = false;
            }
            continue;
        }

        if ($c == '\0' || $c == '\t' || $c == '\r' || $c == '\f' || $c == '\n' || $c == ' ') {
            continue;
        }
        if ($c == '%') {
            $iscomment = true;
            continue;
        }
        if ($c == 'z' && $state === 0) {
            $output .= str_repeat(chr(0), 4);
            continue;
        }
        if ($c < '!' || $c > 'u') {
            return "";
        }
        $code = ord($input[$i]) & 0xff;
        $ords[$state++] = $code - ord('!');

        if ($state == 5) {
            $state = 0;
            for ($sum = 0, $j = 0; $j < 5; $j++) {
                $sum = $sum * 85 + $ords[$j];
            }
            for ($j = 3; $j >= 0; $j--) {
                $output .= chr($sum >> ($j * 8));
            }
        }
    }
    if ($state === 1) {
        return "";
    } else if ($state > 1) {
        for ($i = 0, $sum = 0; $i < $state; $i++) {
            $sum += ($ords[$i] + ($i == $state - 1)) * pow(85, 4 - $i);
        }
        for ($i = 0; $i < $state - 1; $i++) {
            $output .= chr($sum >> ((3 - $i) * 8));
        }
    }

    return $output;
}
/**
 * decode_flate()
 * @param string $input
 */
function decode_flate($input) {
    // The most common compression method for data streams in PDF.
    // Very easy to deal with using libraries.
    return @gzuncompress($input);
}
/**
 * get_object_options()
 * @param object $object
 */
function get_object_options($object) {
    // We need to get current object attributes. These attributes are
    // located between << and >>. Each option starts with /.
    $options = array();
    if (preg_match("#<<(.*)>>#ismU", $object, $options)) {
        // Separate options from each other using /. First empty one should be removed from the array.
        $options = explode("/", $options[1]);
        @array_shift($options);

        // Create handy array for current object attributes
        // Attributs that look like "/Option N" will be written to hash
        // as "Option" => N, and properties like "/Param", will be written as
        // "Param" => true.
        $o = array();
        for ($j = 0; $j < @count($options); $j++) {
            $options[$j] = preg_replace("#\s+#", " ", trim($options[$j]));
            if (strpos($options[$j], " ") !== false) {
                $parts = explode(" ", $options[$j]);
                $o[$parts[0]] = $parts[1];
            } else {
                $o[$options[$j]] = true;
            }
        }
        $options = $o;
        unset($o);
    }

    // Return an array of parameters we found.
    return $options;
}
/**
 * get_decoded_stream()
 * @param string $encodedstream
 * @param array $options
 */
function get_decoded_stream($encodedstream, $options) {
    // Now we have a stream that is possibly coded with some compression method(s)
    // Lets try to decode it.
    $data = "";
    // If current stream has Filter attribute, then is is definately compressed or encoded.
    // Otherwise just return the content.
    if (empty($options["Filter"])) {
        $data = $encodedstream;
    } else {
        // If we know the size of data stream from options then we need to cut the data
        // using this size, or we may not be able to decode it or maybe something else will go wring.
        $length = !empty($options["Length"]) ? $options["Length"] : strlen($encodedstream);
        $stream = substr($encodedstream, 0, $length);

        // Looping through options looking for indicatiors of data compression in the current stream.
        // PDF supprts many different stuff, but text can be coded either by ASCII Hex, or ASCII 85-base or GZ/Deflate
        // We need to look for these keys and apply respecrtive functions for decoding.
        // There is another option: Crypt, but we are not going to work with encrypted PDF's.
        foreach ($options as $key => $value) {
            if ($key == "ASCIIHexDecode") {
                $stream = decode_ascii_hex($stream);
            }
            if ($key == "ASCII85Decode") {
                $stream = decode_ascii85($stream);
            }
            if ($key == "FlateDecode") {
                $stream = decode_flate($stream);
            }
        }
        $data = $stream;
    }
    return $data;
}
/**
 * get_dirty_texts()
 * @param array $texts
 * @param array $textcontainers
 */
function get_dirty_texts(&$texts, $textcontainers) {
    // So we have an array of text contatiners that were taken from both  BT and ET.
    // Our new task is to find a text in them that would be displayed by viewers
    // on the screen. There are many options to do that, Lets check the pair: [...] TJ and Td (...) Tj .
    for ($j = 0; $j < count($textcontainers); $j++) {
        // Add the pieces of row data the we found to the general array of text objects.
        if (preg_match_all("#\[(.*)\]\s*TJ#ismU", $textcontainers[$j], $parts)) {
            $texts = array_merge($texts, @$parts[1]);
        } else if (preg_match_all("#Td\s*(\(.*\))\s*Tj#ismU", $textcontainers[$j], $parts)) {
            $texts = array_merge($texts, @$parts[1]);
        }
    }
}
/**
 * get_char_transformations()
 * @param array $transformations
 * @param string $stream
 */
function get_char_transformations(&$transformations, $stream) {
    // Oh Mama Mia! As far as I know nobody did it before. At least not in the open source.
    // We are going to have some fun now - search in symbol transformation streams.
    // Under transforation I mean conversion of ony symbol to hex form or even to some kind of sequence.
    // We need all the attributes that we can find in the current stream.
    // Data between  beginbfchar and endbfchar transform one hex-code intn another (or sequence of codes)
    // separately. Between beginbfrange and endbfrange the transformation of data sequences is taking place
    // and it reduces the number of definitions.
    preg_match_all("#([0-9]+)\s+beginbfchar(.*)endbfchar#ismU", $stream, $chars, PREG_SET_ORDER);
    preg_match_all("#([0-9]+)\s+beginbfrange(.*)endbfrange#ismU", $stream, $ranges, PREG_SET_ORDER);

    // First of all process separate symbols. Transformaiton string looks as follows:
    // - <0123> <abcd> -> 0123 should be transformed to abcd;
    // - <0123> <abcd6789> -> 0123 should be transformed to many symbols (abcd and 6789 in this case).
    for ($j = 0; $j < count($chars); $j++) {
        // There is a number of strings before data list that we are going ot read. We gonna use it later on.
        $count = $chars[$j][1];
        $current = explode("\n", trim($chars[$j][2]));
        // Read data from each string.
        for ($k = 0; $k < $count && $k < count($current); $k++) {
            // Wrute the transformation we just found. Don't forget about writing leading zeros if there are less then 4 digits..
            if (preg_match("#<([0-9a-f]{2,4})>\s+<([0-9a-f]{4,512})>#is", trim($current[$k]), $map)) {
                $transformations[str_pad($map[1], 4, "0")] = $map[2];
            }
        }
    }
    // Now we can deal with sequences. Manuals are saying that they can be one of two possible types
    // - <0000> <0020> <0a00> -> in this case  <0000> will be substituted with <0a00>, <0001> with <0a01> and so on
    //   till  <0020>, that will be substituted with <0a20>.
    // OR
    // - <0000> <0002> [<abcd> <01234567> <8900>] -> here it works in a bit different way. We need to look how
    //   many elemants are located between <0000> and <0002> (its actually three including 0001). After it we assign to each element
    //   a corresponding value from [ ]: 0000 -> abcd, 0001 -> 0123 4567, а 0002 -> 8900.
    for ($j = 0; $j < count($ranges); $j++) {
        // We need to cross check the number of elements for transformation.
        $count = $ranges[$j][1];
        $current = explode("\n", trim($ranges[$j][2]));
        // Working with each string.
        for ($k = 0; $k < $count && $k < count($current); $k++) {
            // This is first type sequence.
            if (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+<([0-9a-f]{4})>#is", trim($current[$k]), $map)) {
                // Convert data into decimal system: looping will be easier.
                $from = hexdec($map[1]);
                $to = hexdec($map[2]);
                $temp = hexdec($map[3]);

                // We put all the elements from the sequence into transformations array.
                // According to manuals we need also to ass leading zeros if hex-code size is less than 4 symbols.
                for ($m = $from, $n = 0; $m <= $to; $m++, $n++) {
                    $transformations[sprintf("%04X", $m)] = sprintf("%04X", $temp + $n);
                }
                // Second option.
            } else if (preg_match("#<([0-9a-f]{4})>\s+<([0-9a-f]{4})>\s+\[(.*)\]#ismU", trim($current[$k]), $map)) {
                // This is also beginnigna nd end of the sequence. Split data in [ ] by symbols located near to spaces.
                $from = hexdec($map[1]);
                $to = hexdec($map[2]);
                $parts = preg_split("#\s+#", trim($map[3]));

                // Loop through data and assign the new values accordingly.
                for ($m = $from, $n = 0; $m <= $to && $n < count($parts); $m++, $n++) {
                    $transformations[sprintf("%04X", $m)] = sprintf("%04X", hexdec($parts[$n]));
                }
            }
        }
    }
}
/**
 * get_text_using_transformations()
 * @param array $texts
 * @param array $transformations
 */
function get_text_using_transformations($texts, $transformations) {
    // Second phase - getting text out of raw data.
    // In PDF "dirty" text strings may look as follows:
    // - (I love)10(PHP) - in this case text data a re located in  (),
    //   and  10 is number of spaces.
    // - <01234567> - in this case we deal with 2 symbols represented in HEX:
    //   : 0123 and 4567. Substitutions for both should be checked inthe substitution table.
    // - (Hello, \123world!) - here \123 is symbol in octal system and we need to handle it properly.
    // Lets go. We are accumulating text data processign "raw" pieces of text.
    $document = "";
    for ($i = 0; $i < count($texts); $i++) {
        // 2 cases are possible: text can be either in <> (hex) or in () (plain).
        $ishex = false;
        $isplain = false;

        $hex = "";
        $plain = "";
        // Scan current piece of text.
        for ($j = 0; $j < strlen($texts[$i]); $j++) {
            // Get current char.
            $c = $texts[$i][$j];
            // ...and decide what to do with it.
            switch($c) {
                // We have hex data in front of us.
                case "<":
                    $hex = "";
                    $ishex = true;
                break;
                // Hex data are over. Lets parse them.
                case ">":
                    $hexs = str_split($hex, 4);

                    for ($k = 0; $k < count($hexs); $k++) {
                        // If there are less then 4 symbols then the manual says that we need to add zeros after them.
                        $chex = str_pad($hexs[$k], 4, "0");
                        // Checking if current hex-code is already in transformations.
                        // If this is the case change this piece to the required.
                        if (isset($transformations[$chex])) {
                            $chex = $transformations[$chex];
                        }
                        // Write a new Unicode symbol into the output .
                        $document .= html_entity_decode("&#x".$chex.";");
                    }
                    // Hex-sata are over. Need to say it.
                    $ishex = false;
                break;
                // There is a piece of "plain" text.
                case "(":
                    $plain = "";
                    $isplain = true;
                break;
                // Well... this piece will be over sometime.
                case ")":
                    // Get the text we just got into the output stream.
                    $document .= $plain;
                    $isplain = false;
                break;
                // Special symbol. Lets see what is located after it.
                case "\\":
                    $c2 = $texts[$i][$j + 1];
                    // If it is  \ ot either one of ( or ), then print them as it is.
                    if (in_array($c2, array("\\", "(", ")"))) {
                        $plain .= $c2;
                        // If it is empty space of EOL then process it.
                    } else if ($c2 == "n") {
                        $plain .= '\n';
                    } else if ($c2 == "r") {
                        $plain .= '\r';
                    } else if ($c2 == "t") {
                        $plain .= '\t';
                    } else if ($c2 == "b") {
                        $plain .= '\b';
                    } else if ($c2 == "f") {
                        $plain .= '\f';
                        // It might happen that a digit follows after \ . It may be up to 3 of them.
                        // They represent sybmol code in octal system. Lets parse them.
                    } else if ($c2 >= '0' && $c2 <= '9') {
                        // We need 3 digits. No more than 3. Digits only.
                        $oct = preg_replace("#[^0-9]#", "", substr($texts[$i], $j + 1, 3));
                        // Getting the number of characters we already have taken.
                        // We need it to shift the position of current char properly.
                        $j += strlen($oct) - 1;
                        // Put the respective char into "plain" text.
                        $plain .= html_entity_decode("&#".octdec($oct).";");
                    }
                    // We increased the position of current symbol at least by one. Need to inform parser about that.
                    $j++;
                break;

                // If we have something else then write current symbol into temporaty hex string (if we had < before).
                default:
                    if ($ishex) {
                        $hex .= $c;
                    }
                    // Or into "plain" string if ( was opeon.
                    if ($isplain) {
                        $plain .= $c;
                    }
                break;
            }
        }
        // Define text blocks by EOL.
        $document .= "\n";
    }

    // Return text.
    return $document;
}
/**
 * function to convert pdf to plain text
 * @param string $filename
 */
function plagiarisma_pdf2txt($filename) {
    // Read from the pdf file into string keeping in mind that file may contain binary streams.
    $infile = @file_get_contents($filename, FILE_BINARY);
    if (empty($infile)) {
        return "";
    }
    // First iteration. We need to get all the text data from file.
    // We'll get only "raw" data after the firs iteration. These data will include positioning,
    // hex entries, etc.
    $transformations = array();
    $texts = array();

    // Get list of all files from pdf file.
    preg_match_all("#obj(.*)endobj#ismU", $infile, $objects);
    $objects = @$objects[1];

    // Let start the crawling. Apart fromthe text we can meet some other stuff including fonts.
    for ($i = 0; $i < count($objects); $i++) {
        $currentobject = $objects[$i];

        // Check if there is data stream in the current object.
        // Almost all the time it will be compressed with gzip.
        if (preg_match("#stream(.*)endstream#ismU", $currentobject, $stream)) {
            $stream = ltrim($stream[1]);

            // Read the attributes of this object. We are looking only
            // for text, so we have to do minimal cuts to improve the speed.
            $options = get_object_options($currentobject);
            if (!(empty($options["Length1"]) && empty($options["Type"]) && empty($options["Subtype"]))) {
                continue;
            }
            // So, we "may" have text in from of us. Lets decode it from binary file to get the plain text.
            $data = get_decoded_stream($stream, $options);
            if (strlen($data)) {
                // We need to find text container in the current stream.
                // If we will be able to get it the raw text we found will be added to the previous findings.
                if (preg_match_all("#BT(.*)ET#ismU", $data, $textcontainers)) {
                    $textcontainers = @$textcontainers[1];
                    get_dirty_texts($texts, $textcontainers);
                    // Otherwise we'll try to use symbol transformations that we gonna use on the 2nd step.
                } else {
                    get_char_transformations($transformations, $data);
                }
            }
        }
    }
    // After the preliminary parsing of  pdf-document we need to parse
    // the text blocks we got in the context of simbolic transformations. Return the result after we done.
    return get_text_using_transformations($texts, $transformations);
}
/**
 * class to convert .doc, .xls, .ppt to plain text
 *
 * @copyright  2009 Alex Rembish https://github.com/rembish/TextAtAnyCost
 * @copyright  2015 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wcbff {
    // So my little firends, below you can see class that works with WCBFF (Windows Compound Binary File Format).
    // Why do we need it? This format serves as a basement for such "delicious" formats as .doc, .xls, .ppt.
    // Lets see how it looks like.

    /** @var string We gonna read the content of the file we need to decode into this variable. */
    protected $data = "";

    /**
     * Sizes of FAT sector (1 << 9 = 512), Mini FAT sector (1 << 6 = 64) and maximum size
     * of the stream that could be written into a miniFAT.
     * @var int
     */
    protected $sectorshift = 9;
    /** @var int */
    protected $minisectorshift = 6;
    /** @var int */
    protected $minisectorcutoff = 4096;

    /** @var array FAT-sector sequence array and Array of "files" belonging to this file structure. */
    protected $fatchains = array();
    /** @var array */
    protected $fatentries = array();

    /** @var array Array of sequences of  Mini FAT-sectors and the whole Mini FAT of our file. */
    protected $minifatchains = array();
    /** @var string */
    protected $minifat = "";

    /** @var int Version (3 or 4), and way to write numbers (little-endian). */
    private $version = 3;
    /** @var bool */
    private $islittleendian = true;

    /** @var int The number of "files" and the position fo the first "file" in FAT. */
    private $cdir = 0;
    /** @var int */
    private $fdir = 0;

    /** @var int The number of FAT sectors in the file. */
    private $cfat = 0;

    /** @var int The number of miniFAT-sectors and position of sequences of miniFAT-сsectors in the file. */
    private $cminifat = 0;
    /** @var int */
    private $fminifat = 0;

    /** @var array DIFAT: number of such sectors and offset to sector 110 (first 109 sectors are located in the header). */
    private $difat = array();
    /** @var int */
    private $cdifat = 0;
    /** @var int */
    private $fdifat = 0;

    /**
     * Constants: end of sequence (4 bytes).
     */
    const ENDOFCHAIN = 0xFFFFFFFE;
    /**
     * Constants: empty sector (4 bytes).
     */
    const FREESECT   = 0xFFFFFFFF;
    /**
     * read the file into internal variable.
     * @param string $filename
     */
    public function read($filename) {
        $this->data = file_get_contents($filename);
    }
    /**
     * parse()
     */
    public function parse() {
        // First of all we need to check weither we really have CFB in front of us.?
        // To do it we read the first 8 bytes and compare them with 2 patterns: common and the old one.
        $absig = strtoupper(bin2hex(substr($this->data, 0, 8)));
        if ($absig != "D0CF11E0A1B11AE1" && $absig != "0E11FC0DD0CF11E0") {
            return false;
        }
        $this->read_header();
        $this->read_difat();
        $this->read_fat_chains();
        $this->read_mini_fat_chains();
        $this->read_directory_structure();

        // Finally we need to check the root entry in the file structure.
        // This stream is required ot be in a file at least because it has a link
        // to file's miniFAT that we gonna read into $this->miniFAT.
        $restreamid = $this->get_stream_id_by_name("Root Entry");
        if ($restreamid === false) {
            return false;
        }
        $this->minifat = $this->get_stream_by_id($restreamid, true);

        // Remove the unnecessary link to DIFAT-sectors, we have "stolen" complete FAT sequences instead of them.
        unset($this->difat);

        // After all this we should be able to work with any of the "upper" formats from Microsoft such as doc, xls, ppt.
    }
    /**
     * function that looks for stream number in the directory structure by its name.
     * it returns false if nothing was found.
     * @param string $name
     */
    public function get_stream_id_by_name($name) {
        for ($i = 0; $i < count($this->fatentries); $i++) {
            if ($this->fatentries[$i]["name"] == $name) {
                return $i;
            }
        }
        return false;
    }
    /**
     * function gets the stream number ($id) and a second parameter (second perameter is required for the root entry only).
     * it returns the binary content fo this stream.
     * @param integer $id
     * @param boolean $isroot
     */
    public function get_stream_by_id($id, $isroot = false) {
        $entry = $this->fatentries[$id];
        // Get the size and offset position to the content of "current" file.
        $from = $entry["start"];
        $size = $entry["size"];

        // Now 2 options are possible: is size is less than  4096 byte, then we need ot read data
        // from MiniFAT. If more than 4096 read from the common FAT. RootEntry is an exclusion:
        // we need ot read contents from FAT as miniFAT is located there.

        $stream = "";
        // So, here is the 1st option: small size and not root.
        if ($size < $this->minisectorcutoff && !$isroot) {
            // Get the miniFAT sector size - 64 bytes.
            $ssize = 1 << $this->minisectorshift;

            do {
                // Get the offset in miniFAT.
                $start = $from << $this->minisectorshift;
                // Read miniFAT-sector.
                $stream .= substr($this->minifat, $start, $ssize);
                // Get the next piece of miniFAT in the array of chains.
                $from = $this->minifatchains[$from];
                // While not end of chain (sequence).
            } while ($from != self::ENDOFCHAIN);
        } else {
            // Second option - large piece - read it from FAT.
            // Get the sector size  - 512 (or 4096 for new versions).
            $ssize = 1 << $this->sectorshift;

            do {
                // Getting the offset in the file (taking into account that there is a header of 512 bytes in the begining).
                $start = ($from + 1) << $this->sectorshift;
                // Read a sector.
                $stream .= substr($this->data, $start, $ssize);
                // Get the next sector inthe array of FAT chains.
                $from = $this->fatchains[$from];
                // While not end of chain (sequence).
            } while ($from != self::ENDOFCHAIN);
        }
        // Return the stream content according to its size.
        return substr($stream, 0, $size);
    }
    /**
     * this function reads data from file header.
     */
    private function read_header() {
        // We need to get the information about the data format in the file.
        $ubyteorder = strtoupper(bin2hex(substr($this->data, 0x1C, 2)));
        // We need to check if it is  little-endian record.
        $this->islittleendian = $ubyteorder == "FEFF";

        // Version 3 or 4 (never actually met 4th, but its description appears in the manual).
        $this->version = $this->get_short(0x1A);

        // Offsets for FAT and miniFAT.
        $this->sectorshift = $this->get_short(0x1E);
        $this->minisectorshift = $this->get_short(0x20);
        $this->minisectorcutoff = $this->get_long(0x38);

        // Number of entries in the directory and offset to the first description in the file.
        if ($this->version == 4) {
            $this->cdir = $this->get_long(0x28);
        }
        $this->fdir = $this->get_long(0x30);

        // Number of FAT sectors in the file.
        $this->cfat = $this->get_long(0x2C);

        // Number and position of hte 1st miniFAT-sector of sequences.
        $this->cminifat = $this->get_long(0x40);
        $this->fminifat = $this->get_long(0x3C);

        // Where are the FAT sector chains and how many of them are there.
        $this->cdifat = $this->get_long(0x48);
        $this->fdifat = $this->get_long(0x44);
    }
    /**
     * so.... DIFAT. DIFAT shows in which sectors we can find descriptions of FAT sector chains.
     * without these chains we won't be able to get stream contents in fragmented files.
     */
    private function read_difat() {
        $this->difat = array();
        // First 109 links to sequences are being stored in the header of our file.
        for ($i = 0; $i < 109; $i++) {
            $this->difat[$i] = $this->get_long(0x4C + $i * 4);
        }
        // We also check if there are other links to chains. in small (upto 8.5MB) there is no such
        // links but in larger files we have to read them.
        if ($this->fdifat != self::ENDOFCHAIN) {
            // Sector size and start position to read links.
            $size = 1 << $this->sectorshift;
            $from = $this->fdifat;
            $j = 0;

            do {
                // Get the position in the file considering header.
                $start = ($from + 1) << $this->sectorshift;
                // Read the links to sequences' sectors.
                for ($i = 0; $i < ($size - 4); $i += 4) {
                    $this->difat[] = $this->get_long($start + $i);
                }
                // Getting the next  DIFAT-sector. Link to this sector is written
                // as the last "word" in the current  DIFAT-sector.
                $from = $this->get_long($start + $i);
                // Ef sector exists we need to move there.
            } while ($from != self::ENDOFCHAIN && ++$j < $this->cdifat);
        }

        // Remove the unnecessary links.
        while ($this->difat[count($this->difat) - 1] == self::FREESECT) {
            array_pop($this->difat);
        }
    }
    /**
     * so, we done with reading DIFAT. Now chains of FAT sectors should be converted .
     * lets go further.
     */
    private function read_fat_chains() {
        // Sector size.
        $size = 1 << $this->sectorshift;
        $this->fatchains = array();

        // Going through  DIFAT array.
        for ($i = 0; $i < count($this->difat); $i++) {
            // Go to the sector that we were looking for (with the header).
            $from = ($this->difat[$i] + 1) << $this->sectorshift;
            // Getting the FAT chain: array index is a current sector,
            // value from an array s index of the next element or
            // ENDOFCHAIN - if it is last element in the chain.
            for ($j = 0; $j < $size; $j += 4) {
                $this->fatchains[] = $this->get_long($from + $j);
            }
        }
    }
    /**
     * we done with reading of FAT sequences. Now heed to read MiniFAT-sequences exaactly the same way.
     */
    private function read_mini_fat_chains() {
        // Sector size.
        $size = 1 << $this->sectorshift;
        $this->minifatchains = array();

        // Looking for the first sector with MiniFAT- sequences.
        $from = $this->fminifat;
        // If MiniFAT appears to be in file then.
        while ($from != self::ENDOFCHAIN) {
            // Looking for the offset to the sector with MiniFat-sequence.
            $start = ($from + 1) << $this->sectorshift;
            // Read the sequence from the current sector.
            for ($i = 0; $i < $size; $i += 4) {
                $this->minifatchains[] = $this->get_long($start + $i);
            }
            // If this is notthe last sector in the chain we need to move forward.
            $from = $this->fatchains[$from];
        }
    }
    /**
     * the most important functions that reads structure of "files" of such a type
     * all the FS objects are written into this structure.
     */
    private function read_directory_structure() {
        // Get the 1st sector with "files" in file system.
        $from = $this->fdir;
        // Get the sector size.
        $size = 1 << $this->sectorshift;
        $this->fatentries = array();
        do {
            $start = ($from + 1) << $this->sectorshift;
            // Let go through the content of this sector. One sector contains up to 4  (or 128 for version 4)
            // entries to FS. Lets read them.
            for ($i = 0; $i < $size; $i += 128) {
                // Get the binary data.
                $entry = substr($this->data, $start + $i, 128);
                $this->fatentries[] = array(
                    "name" => $this->utf16_to_ansi(substr($entry, 0, $this->get_short(0x40, $entry))),
                    "type" => ord($entry[0x42]),
                    "color" => ord($entry[0x43]),
                    "left" => $this->get_long(0x44, $entry),
                    "right" => $this->get_long(0x48, $entry),
                    "child" => $this->get_long(0x4C, $entry),
                    "start" => $this->get_long(0x74, $entry),
                    "size" => $this->get_some_bytes($entry, 0x78, 8),
                );
            }

            // Get the next sector with descriptions and jump there.
            $from = $this->fatchains[$from];
            // Of course if such a sector exists.
        } while ($from != self::ENDOFCHAIN);

        // Remove "empty" entries  at the end if any.
        while ($this->fatentries[count($this->fatentries) - 1]["type"] == 0) {
            array_pop($this->fatentries);
        }
    }
    /**
     * support function to get the adequate name of the current entrie in FS.
     * note: names are written in the Unicode.
     * @param string $in
     */
    private function utf16_to_ansi($in) {
        $out = "";
        for ($i = 0; $i < strlen($in); $i += 2) {
            $out .= chr($this->get_short($i, $in));
        }
        return trim($out);
    }
    /**
     * function to convert from Unicode to UTF8.
     * @param string $in
     * @param boolean $check
     */
    protected function unicode_to_utf8($in, $check = false) {
        $out = "";
        if ($check && strpos($in, chr(0)) !== 1) {
            while (($i = strpos($in, chr(0x13))) !== false) {
                $j = strpos($in, chr(0x15), $i + 1);
                if ($j === false) {
                    break;
                }
                $in = substr_replace($in, "", $i, $j - $i);
            }
            for ($i = 0; $i < strlen($in); $i++) {
                if (ord($in[$i]) >= 32) {
                    usleep(1);
                } else if ($in[$i] == ' ' || $in[$i] == '\n') {
                    usleep(1);
                } else {
                    $in = substr_replace($in, "", $i, 1);
                }
            }
            $in = str_replace(chr(0), "", $in);

            return $in;
        } else if ($check) {
            while (($i = strpos($in, chr(0x13).chr(0))) !== false) {
                $j = strpos($in, chr(0x15).chr(0), $i + 1);
                if ($j === false) {
                    break;
                }
                $in = substr_replace($in, "", $i, $j - $i);
            }
            $in = str_replace(chr(0).chr(0), "", $in);
        }

        // Loop through 2 byte words.
        $skip = false;
        for ($i = 0; $i < strlen($in); $i += 2) {
            $cd = substr($in, $i, 2);
            if ($skip) {
                if (ord($cd[1]) == 0x15 || ord($cd[0]) == 0x15) {
                    $skip = false;
                }
                continue;
            }

            // If upper byte is  0 then this is ANSI.
            if (ord($cd[1]) == 0) {
                // If ASCII value is higher than 32 we will write it as it is.
                if (ord($cd[0]) >= 32) {
                    $out .= $cd[0];
                } else if ($cd[0] == ' ' || $cd[0] == '\n') {
                    $out .= $cd[0];
                } else if (ord($cd[0]) == 0x13) {
                    $skip = true;
                } else {
                    continue;

                    switch (ord($cd[0])) {
                        case 0x0D: case 0x07:
                            $out .= "\n";
                        break;
                        case 0x08: case 0x01:
                            $out .= "";
                        break;
                        case 0x13:
                            $out .= "HYPER13";
                        break;
                        case 0x14:
                            $out .= "HYPER14";
                        break;
                        case 0x15:
                            $out .= "HYPER15";
                        break;
                        default:
                            $out .= " ";
                        break;
                    }
                }
            } else {
                if (ord($cd[1]) == 0x13) {
                    echo "@";
                    $skip = true;
                    continue;
                }
                $out .= "&#x".sprintf("%04x", $this->get_short(0, $cd)).";";
            }
        }
        return $out;
    }
    /**
     * support function to geto some bytes from the string
     * taking into account order of bytes and converting values into a number.
     * @param string $data
     * @param string $from
     * @param integer $count
     */
    protected function get_some_bytes($data, $from, $count) {
        // Read data from  $data by default.
        if ($data === null) {
            $data = $this->data;
        }
        $string = substr($data, $from, $count);

        if ($this->islittleendian) {
            $string = strrev($string);
        }
        // Encode from binary to hex and to a number.
        return hexdec(bin2hex($string));
    }
    /**
     * read a word from the variable (by default from this->data).
     * @param string $from
     * @param string $data
     */
    protected function get_short($from, $data = null) {
        return $this->get_some_bytes($data, $from, 2);
    }
    /**
     * read a double word from the variable (by default from this->data).
     * @param string $from
     * @param string $data
     */
    protected function get_long($from, $data = null) {
        return $this->get_some_bytes($data, $from, 4);
    }
}
/**
 * class to work with Microsoft Word Document (or just doc).
 *
 * @copyright  2009 Alex Rembish https://github.com/rembish/TextAtAnyCost
 * @copyright  2015 Plagiarisma.Net http://plagiarisma.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class justdoc extends wcbff {
    /**
     * this function extends parse function and returns text from the file.
     * if returns false if something went wrong.
     */
    public function parse() {
        parent::parse();

        // To read a DOC file we need 2 streams - WordDocument and 0Table or
        // 1Table depending on the situation. Lets get hte first stream.
        // It contains pieces of text we need to collect.
        $wdstreamid = $this->get_stream_id_by_name("WordDocument");
        if ($wdstreamid === false) {
            return false;
        }
        // We got the stream. Lets read it into a variable.
        $wdstream = $this->get_stream_by_id($wdstreamid);

        // Next we need to get something from  FIB - special block named.
        // File Information Block that is located in the beginning of WordDocument stream.
        $bytes = $this->get_short(0x000A, $wdstream);

        // Read which table we need to read: number 0 or number 1.
        // To do so we need to read a small bit from the header.
        $fwhichtblstm = ($bytes & 0x0200) == 0x0200;

        // Now we need to get the position of  CLX in the table stream. And the size of CLX itself.
        $fcclx = $this->get_long(0x01A2, $wdstream);
        $lcbclx = $this->get_long(0x01A6, $wdstream);

        // Conting few values to separate positions from the size in clx.
        $ccptext = $this->get_long(0x004C, $wdstream);
        $ccpftn = $this->get_long(0x0050, $wdstream);
        $ccphdd = $this->get_long(0x0054, $wdstream);
        $ccpmcr = $this->get_long(0x0058, $wdstream);
        $ccpatn = $this->get_long(0x005C, $wdstream);
        $ccpedn = $this->get_long(0x0060, $wdstream);
        $ccptxbx = $this->get_long(0x0064, $wdstream);
        $ccphdrtxbx = $this->get_long(0x0068, $wdstream);

        // Using the value that we just got we can look for the value of the last CP - character position.
        $lastcp = $ccpftn + $ccphdd + $ccpmcr + $ccpatn + $ccpedn + $ccptxbx + $ccphdrtxbx;
        $lastcp += ($lastcp != 0) + $ccptext;

        // Get the required table in the file.
        $tstreamid = $this->get_stream_id_by_name(intval($fwhichtblstm)."Table");
        if ($tstreamid === false) {
            return false;
        }
        // And read the stream to a variable.
        $tstream = $this->get_stream_by_id($tstreamid);
        $clx = substr($tstream, $fcclx, $lcbclx);

        // Now we need to go through  CLX (yes... its complex) looking for piece with offsets and sizes of text pieces.
        $lcbpiecetable = 0;
        $piecetable = "";

        // Well... this is the most exciting part. There is not too much of documentation on the web site about
        // what can be found before  pieceTable in the  CLX. So we will do the total search looking
        // for the possible beginning of pieceTable (it must start with  0х02), and read the following 4 bytes
        // - size of pieceTable. If the actual size equial to size writtent in the offset then Bingo! we found pieceTable.
        // If not continue the search.

        $from = 0;
        // Looking for  0х02 in CLX starting from the current offset.
        while (($i = strpos($clx, chr(0x02), $from)) !== false) {
            // Get the pieceTable size.
            $lcbpiecetable = $this->get_long($i + 1, $clx);
            // Get the  pieceTable.
            $piecetable = substr($clx, $i + 5);

            // If the real size differs from required then this is not what we are lloking for.
            // Skip it.
            if (strlen($piecetable) != $lcbpiecetable) {
                $from = $i + 1;
                continue;
            }
            // Oh.... we got it!!! its break time  my littel friends!
            break;
        }

        // Now we need to fill the array of  character positions, until we got the last  CP.
        $cp = array(); $i = 0;
        while (($cp[] = $this->get_long($i, $piecetable)) != $lastcp) {
            $i += 4;
        }
        // The rest will go as PCD (piece descriptors).
        $pcd = str_split(substr($piecetable, $i + 4), 8);

        $text = "";
        // Yes! we came to our main goal - reading text from file.
        // Go through the descriptors of such pieces.
        for ($i = 0; $i < count($pcd); $i++) {
            // Get the word with offset and  compression flag.
            $fcvalue = $this->get_long(2, $pcd[$i]);
            // Check what do we have: simple ANSI or Unicode.
            $isansi = ($fcvalue & 0x40000000) == 0x40000000;
            // The rest without top will go as an offset.
            $fc = $fcvalue & 0x3FFFFFFF;

            // Get the piece of text.
            $lcb = $cp[$i + 1] - $cp[$i];
            // If this is Unicode, then lets read twice more bytes.
            if (!$isansi) {
                $lcb *= 2;
                // If ANSI - start twice earlier.
            } else {
                $fc /= 2;
            }
            // Read a piece from Worddocument stream considering the offset.
            $part = substr($wdstream, $fc, $lcb);
            // If this is a Unicode text then decode it to the regular text.
            if (!$isansi) {
                $part = $this->unicode_to_utf8($part);
            }
            // Add a piece.
            $text .= $part;
        }

        // Remove entries with embedded objects from the file.
        $text = preg_replace("/HYPER13 *(INCLUDEPICTURE|HTMLCONTROL)(.*)HYPER15/iU", "", $text);
        $text = preg_replace("/HYPER13(.*)HYPER14(.*)HYPER15/iU", "$2", $text);
        // Return the results.
        return $text;
    }
    /**
     * function to convert from Unicode to UTF8.
     * @param string $in
     * @param boolean $check
     */
    protected function unicode_to_utf8($in, $check=false) {
        $out = "";
        // Loop through 2-byte sequences.
        for ($i = 0; $i < strlen($in); $i += 2) {
            $cd = substr($in, $i, 2);

            // If the first byte is 0 then this is ANSI.
            if (ord($cd[1]) == 0) {
                // If ASCII value of the low byte is higher than 32 then write it as it is.
                if (ord($cd[0]) >= 32) {
                    $out .= $cd[0];
                }
                // Otherwise check symbols against embedded commands.
                switch (ord($cd[0])) {
                    case 0x0D: case 0x07:
                        $out .= "\n";
                    break;
                    case 0x08: case 0x01:
                        $out .= "";
                    break;
                    case 0x13:
                        $out .= "HYPER13";
                    break;
                    case 0x14:
                        $out .= "HYPER14";
                    break;
                    case 0x15:
                        $out .= "HYPER15";
                    break;
                }
            } else { // Otherwise convert to  HTML entity.
                $out .= html_entity_decode("&#x".sprintf("%04x", $this->get_short(0, $cd)).";");
            }
        }

        // And... return the result.
        return $out;
    }
}
/**
 * function to convert doc to plain text. For those who "don't need classes".
 * @param string $filename
 */
function plagiarisma_doc2txt($filename) {
    $doc = new justdoc;
    $doc->read($filename);
    return $doc->parse();
}
