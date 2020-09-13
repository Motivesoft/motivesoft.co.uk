<?php
  header('Content-Type: application/rss+xml' );

  # Cache this to prevent >500 reads/month
  # http://api.wunderground.com/api/31ce205e9e18ba9c/alerts/geolookup/forecast/astronomy/conditions/almanac/q/ES/Gran_Alacant.json
  # http://api.wunderground.com/api/31ce205e9e18ba9c/alerts/geolookup/forecast/q/ES/Gran_Alacant.json
  # http://api.wunderground.com/api/31ce205e9e18ba9c/alerts/geolookup/forecast/astronomy/conditions/almanac/q/zmw:00000.58.08360.json
  $country = $_GET['country'];
  $city = $_GET['city'];
  $zmw = $_GET['zmw'];

  $frag_url = "";
  $frag_file = "";
  if( $zmw ) {
    $frag_url = "zmw:$zmw";
    $frag_file = $zmw;
  } else {
    if( !$country ) {
      echo "Country is required\n";
      exit;
    }
    if( !$city ) {
      echo "City is required\n";
      exit;
    }

    $frag_url = "$country/$city";
    $frag_file = "$country-$city";
  }

  # Debugging stuff
  if( FALSE ) {
    # autocomplete.wunderground.com/aq?query=Gran%20Alacant&c=ES
    # zmw:00000.58.08360
    # Validate the parameters and get the actual query string
    # then use the zmw for the local cache filename
    # obviously error out if the query returns other than one result
    $city_normalized = $city;
    $city_normalized = str_replace( "_", "%20", $city_normalized );
    $city_normalized = str_replace( " ", "%20", $city_normalized );
    $query = "http://autocomplete.wunderground.com/aq?query=$city_normalized&c=$country";

    $results_json = file_get_contents( $query );
    if( $results_json ) {
      $result = json_decode( $results_json )->{'RESULTS'};
      if( sizeof( $result ) == 1 ) {
        $zmw = $result[0]->{'zmw'};
	    $frag_url = "zmw:$zmw";
	    $frag_file = $zmw;
        echo "$country and $city = $zmw\n"; #uncomment to use
        exit; #uncomment to use
      } else {
        echo "Could not identify $country and $city\n";
        exit;
      }
    } else {
      echo "Could not validate $country and $city\n";
      exit;
    }
  }

  # $source = "debug-$frag_file.json"; # during debugging
  # $source = "http://api.wunderground.com/api/31ce205e9e18ba9c/alerts/geolookup/forecast/astronomy/conditions/almanac/q/$country/$city.json";
  # $source = "http://api.wunderground.com/api/31ce205e9e18ba9c/alerts/geolookup/forecast/astronomy/conditions/almanac/q/zmw:00000.58.08360.json";
  $source = "http://api.wunderground.com/api/31ce205e9e18ba9c/alerts/geolookup/forecast/astronomy/conditions/almanac/q/$frag_url.json";

  # Unique and meaningful cache name that doesn't need hard coding
  $cache_filename = "$frag_file.json";
  $cache_max_age_in_seconds = 60 * 60;

  $update = TRUE;
  $now = time();

  # Test the need to update the cache
  if( file_exists( $cache_filename ) ) {
    $cache_date = filemtime( $cache_filename );
    $age = ($now - $cache_date);

    if( $age < $cache_max_age_in_seconds ) {
      $update = FALSE;
    }
  }

  # Update the cache
  if( $update ) {
    $contents = file_get_contents( $source );

    if( $contents ) {
      file_put_contents( $cache_filename, $contents );
    }
  }

  # Only output something if the cache is available
  if( file_exists( $cache_filename ) ) {
    $update_date = filemtime( $cache_filename );
    $json_string = file_get_contents( $cache_filename );
    $parsed_json = json_decode( $json_string );

    if( json_last_error() == JSON_ERROR_UTF8 ) {
      $json_string = utf8_encode( $json_string );
      $parsed_json = json_decode( $json_string );
    }

    $location = $parsed_json->{'location'}->{'city'};

    $alerts = $parsed_json->{'alerts'};
    $count = sizeof( $alerts );

    date_default_timezone_set( $parsed_json->{'location'}->{'tz_short'} );

    rss_header( "Weather alerts for ${location}", $update_date );

    if( sizeof( $alerts ) > 0 ) {
      foreach( $alerts as $alert ) {
        issue_alert( $alert );
      }
    } else {
      rss_item_start( date( "r" ) );
      echo "There are no current weather alerts for ${location}";
      rss_item_end();
    }

    rss_footer();
  } else {
    # Would it be better to return nothing?
    rss_header( "Weather alerts unavailable", $now );
    rss_footer();
  }

  function issue_alert( $a ) {
    $alarm_level = $a->{'level_meteoalarm_name'};
    $alarm_wtype = $a->{'wtype_meteoalarm_name'};
    $description = $a->{'description'};

    rss_item_start( $a->{'date'} );
    echo "$alarm_level $alarm_wtype alert. $description";
    rss_item_end();
  }

  function rss_item_start( $d ) {
    echo '    <item>';
    echo "\n";
    echo "      <title>$d</title>";
    echo "\n";
    echo '      <guid isPermaLink="false">';
    echo "$d</guid>\n";
    echo "      <pubDate>$d</pubDate>";
    echo "\n";
    echo '      <link>http://www.wunderground.com/weather/api/d/terms.html</link>';
    echo "\n";
    echo '      <description>';
  }

  function rss_item_end() {
    echo '</description>';
    echo "\n";
    echo '    </item>';
    echo "\n";
  }

  function rss_header( $title, $pubDate ) {
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo "\n";
#    echo '<rss xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:georss="http://www.georss.org/georss" version="2.0">';
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">';
    echo "\n";
    echo "  <channel>";
    echo "\n";
    echo "    <title>$title</title>";
    echo "\n";
    echo "    <link>http://www.wunderground.com/</link>";
    echo "\n";
    echo '    <description>Latest weather alerts from Weather Underground.</description>';
    echo "\n";
    echo '    <language>en</language>';
    echo "\n";
    echo '    <copyright>Copyright: (C) Weather Underground LLC, see http://www.wunderground.com/weather/api/d/terms.html for more details</copyright>';
    echo "\n";
    # $date = date( "D, d M y H:i:s T", $pubDate );
    $date = date( "r" );
    echo "    <pubDate>$date</pubDate>";
    echo "\n";
    echo '    <atom:link href="';
#    echo "motivesoft.co.uk/alexa/weather/alerts.php?zmw=00000.58.08360"
    echo "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    echo '" rel="self" type="application/rss+xml" />';
    echo "\n";
  }

  function rss_footer() {
    echo '  </channel>';
    echo "\n";
    echo '</rss>';
    echo "\n";
  }
?>
