<?php

const LOCATION = [28.5383, -81.3792]; // Lat + Lng for Orlando FL
const WMOCODES = [
    0 => 'Clear',
    1 => 'Mostly Clear',
    2 => 'Partly Cloudy',
    3 => 'Overcast',
    45 => 'Foggy',
    48 => 'Foggy',
    51 => 'Light Drizzle',
    53 => 'Drizzle',
    55 => 'Heavy Drizzle',
    56 => 'Frz Drizzle',
    57 => 'Frz Drizzle',
    61 => 'Slight Rain',
    63 => 'Rain',
    65 => 'Heavy Rain',
    66 => 'Light Frz Rain',
    67 => 'Heavy Frz Rain',
    71 => 'Light Snow',
    73 => 'Snow',
    75 => 'Heavy Snow',
    77 => 'Snow',
    80 => 'Light Showers',
    81 => 'Showers',
    82 => 'Heavy Showers',
    85 => 'Snow Showers',
    86 => 'Heavy Snow Showers',
    95 => 'Thunderstorms',
    96 => 'Hail Storms',
    99 => 'Heavy Hail Storms',
];
const STOCKS = ['DIA', 'SPY'];
const STOCKSURL = "https://api.twelvedata.com/quote";
const STOCKSKEY = "YOUR_STOCKS_KEY";
const NEWS = "https://api.nytimes.com/svc/mostpopular/v2/viewed/1.json";
const NEWSKEY = "YOUR_NEWS_KEY";
const MAXNEWS = 3;
const SUBREDDITS = ['science', 'upliftingnews', 'technology', 'fauxmoi', 'todayilearned'];
const BOXWIDTH = 78;

// Get weather data
echo "Fetching weather data..." . PHP_EOL;
$weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude=" . LOCATION[0] . "&longitude=" . LOCATION[1] . "&daily=weather_code,temperature_2m_max,temperature_2m_min,sunrise,sunset,daylight_duration,wind_speed_10m_max&temperature_unit=fahrenheit&wind_speed_unit=mph&precipitation_unit=inch&timezone=America%2FNew_York&forecast_days=1";
$weatherData = json_decode(file_get_contents($weatherUrl), true);

if (empty($weatherData) || !isset($weatherData['daily'])) {
    die("Unable to retrieve weather data");
}

// Get stock ticker data
echo "Fetching stock ticker data..." . PHP_EOL;
$stockData = [];
foreach (STOCKS as $stock) {
    $stockData[$stock] = json_decode(file_get_contents(STOCKSURL . '?symbol=' . $stock . '&apikey=' . STOCKSKEY), true);

    if (empty($stockData[$stock])) {
        die("Unable to retrieve stock data for " . $stock);
    }
}

// Get news headlines data
echo "Fetching news headlines data..." . PHP_EOL;
$newsUrl = NEWS . "?api-key=" . NEWSKEY;
$newsData = [];
$newsAmount = 0;

$data = json_decode(file_get_contents($newsUrl), true);

if (!isset($data['results'])) {
    die("Unable to retrieve news data");
}

foreach ($data['results'] as $article) {
    if (
        ($article['type'] === 'Article') &&
        (in_array($article['section'], ['U.S.', 'World', 'Weather', 'Arts'])) &&
        ($newsAmount < MAXNEWS)
    ) {
        $newsData[] = $article;
        $newsAmount++;
    }
}

// Get reddit top posts data
echo "Fetching reddit top posts data..." . PHP_EOL;
$redditUrl = "https://reddit.com/r/";
$redditData = [];

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "Accept-language: en\r\n" .
            "Accept: application/json\r\n" .
            "Content-Type: application/json\r\n" .
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36\r\n"
    ]
];

$context = stream_context_create($opts);

foreach (SUBREDDITS as $subreddit) {
    $data = json_decode(file_get_contents($redditUrl . $subreddit . ".json", false, $context), true);
    if (!isset($data['data']) || !isset($data['data']['children'])) {
        die("Unable to fetch reddit data for r/" . $subreddit);
    }

    $children = array_filter($data['data']['children'], function($child) {
        return ($child['data']['stickied'] === false) && ($child['data']['media'] === null);
    });

    if (empty($children)) {
        die("Unable to get non-stickied, non-media posts for r/" . $subreddit);
    }

    usort($children, fn ($a, $b) => $b['data']['ups'] <=> $a['data']['ups']);

    $redditData[$subreddit] = $children[0]['data'];
}

// Open a connection to the printer
echo "Writing to printer..." . PHP_EOL;
$printer = fopen("/dev/usb/lp0", "w");

if (!$printer) {
    die("Unable to open printer at /dev/usb/lp0");
}

// Try to switch to CP437
fwrite($printer, "\x1Bt\x00");  // ESC t 0

// Define box-drawing characters in CP437
$topLeft = "\xC9";
$topRight = "\xBB";
$bottomLeft = "\xC8";
$bottomRight = "\xBC";
$horizontalDouble = "\xCD";
$verticalDouble = "\xBA";
$sepLeftDouble = "\xCC";
$sepRightDouble = "\xB9";
$sepLeft = "\xC7";
$sepRight = "\xB6";
$horizontalSingle = "\xC4";
$deg = "\xF8";
$upArrow = "\x06";
$bullet = "\x07";

// Assemble header
$header = str_repeat(" ", 30) . $topLeft . str_repeat($horizontalDouble, 19) . $topRight . str_repeat(" ", 29) . "\n";
$header .= $topLeft . str_repeat($horizontalDouble, 29) . $verticalDouble . " SCHMELYUN TRIBUNE " . $verticalDouble . str_repeat($horizontalDouble, 28) . $topRight . "\n";
$header .= $verticalDouble . "   " . strtoupper(date("D")) . str_repeat(" ", 23) . $bottomLeft . str_repeat($horizontalDouble, 19) . $bottomRight . str_repeat(" ", 14) . strtoupper(date("M j Y")) . "   " . $verticalDouble . "\n";
$header .= $bottomLeft . str_repeat($horizontalDouble, BOXWIDTH) . $bottomRight . "\n";
$header .= "\n";

// Assemble weather
$weather = str_repeat($horizontalSingle, 3) . " WEATHER " . str_repeat($horizontalSingle, (BOXWIDTH - 10)) . "\n";
$weather .= "\n";
$weather .= "   " . WMOCODES[$weatherData['daily']['weather_code'][0]] . "  -  High: " . $weatherData['daily']['temperature_2m_max'][0] . "f  -  Low: " . $weatherData['daily']['temperature_2m_min'][0] . "f  -  Wind: " . $weatherData['daily']['wind_speed_10m_max'][0] . "mph" . "\n";
$weather .= "   " . round(($weatherData['daily']['daylight_duration'][0] / 3600), 2) . "h of Sunlight  -  Sunrise: " . date('g:ia', strtotime($weatherData['daily']['sunrise'][0])) . "  -  Sunset: " . date('g:ia', strtotime($weatherData['daily']['sunset'][0])) . "\n";
$weather .= "\n\n";

// Assemble markets
$markets = str_repeat($horizontalSingle, 3) . " MARKETS " . str_repeat($horizontalSingle, (BOXWIDTH - 10)) . "\n";
$markets .= "\n";
foreach ($stockData as $stock => $data) {
    $markets .= "   " . $stock . ": " . round($data['close'], 2) . " (" . round($data['percent_change'], 2) . ")  -  52 Week: " . round($data['fifty_two_week']['low'], 2) . " to " . round($data['fifty_two_week']['high'], 2) . "\n";
}
$markets .= "\n\n";

// Assemble headlines
$headlines = str_repeat($horizontalSingle, 3) . " HEADLINES " . str_repeat($horizontalSingle, (BOXWIDTH - 12)) . "\n";
$headlines .= "\n";
foreach ($newsData as $article) {
    foreach (splitString($article['title'], 75) as $titlePiece) {
        $headlines .= line(strtoupper($titlePiece)) . "\n";
    }
    foreach (splitString($article['abstract'], 75) as $abstractPiece) {
        $headlines .= line($abstractPiece) . "\n";
    }
    $headlines .= "\n";
}
$headlines .= "\n";

// Assemble reddit
$reddit = str_repeat($horizontalSingle, 3) . " REDDIT " . str_repeat($horizontalSingle, (BOXWIDTH - 9)) . "\n";
$reddit .= "\n";
foreach ($redditData as $sub => $item) {
    foreach (splitString($item['title'], 75) as $titlePiece) {
        $reddit .= line($titlePiece) . "\n";
    }
    $reddit .= "   " . "> r/" . $sub . "  -  " . $item['ups'] . " upvotes" . "\n";
    $reddit .= "\n";
}
$reddit .= "\n";

// Assemble footer
$footer = "\n\n";

// Print header
fwrite($printer, $header);

// Print weather
fwrite($printer, $weather);

// Print markets
fwrite($printer, $markets);

// Print headlines
fwrite($printer, $headlines);

// Print reddit
fwrite($printer, $reddit);

// Print footer
fwrite($printer, $footer);

// Ensure all data is written and close the connection
fflush($printer);
fclose($printer);

echo "Message sent to printer.\n";

function line($str)
{
    $str = str_replace("—", "-", $str);
    $str = convertCurlyQuotes($str);
    return str_repeat(" ", 3) . $str;
}

function splitString($string, $maxLength = 80) {
    $result = [];
    $words = explode(' ', $string);
    $currentLine = '';

    foreach ($words as $word) {
        if (strlen($currentLine . $word) <= $maxLength) {
            $currentLine .= ($currentLine ? ' ' : '') . $word;
        } else {
            if ($currentLine) {
                $result[] = $currentLine;
                $currentLine = $word;
            } else {
                // If a single word is longer than maxLength, split it
                $result[] = substr($word, 0, $maxLength);
                $currentLine = substr($word, $maxLength);
            }
        }
    }

    if ($currentLine) {
        $result[] = $currentLine;
    }

    return $result;
}

function convertCurlyQuotes($text): string
{
    $quoteMapping = [
        "\xC2\x82"     => "'",
        "\xC2\x84"     => '"',
        "\xC2\x8B"     => "'",
        "\xC2\x91"     => "'",
        "\xC2\x92"     => "'",
        "\xC2\x93"     => '"',
        "\xC2\x94"     => '"',
        "\xC2\x9B"     => "'",
        "\xC2\xAB"     => '"',
        "\xC2\xBB"     => '"',
        "\xE2\x80\x98" => "'",
        "\xE2\x80\x99" => "'",
        "\xE2\x80\x9A" => "'",
        "\xE2\x80\x9B" => "'",
        "\xE2\x80\x9C" => '"',
        "\xE2\x80\x9D" => '"',
        "\xE2\x80\x9E" => '"',
        "\xE2\x80\x9F" => '"',
        "\xE2\x80\xB9" => "'",
        "\xE2\x80\xBA" => "'",
        "&ldquo;"      => '"',
        "&rdquo;"      => '"',
        "&lsquo;"      => "'",
        "&rsquo;"      => "'",
    ];

    return strtr(html_entity_decode($text, ENT_QUOTES, "UTF-8"), $quoteMapping);
}
