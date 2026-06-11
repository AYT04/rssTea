<?php
$urls = file('feeds.txt', FILE_IGNORE_NEW_LINES);
$postLimit = 5;
$result = '';
$feeda = array();
$feedn = 0;

foreach ($urls as $url) {
    echo "\n"."Getting feed from: ". $url." \n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36',
        CURLOPT_REFERER => 'https://www.google.com/',
        CURLOPT_TIMEOUT_MS => 7000,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch) || $httpcode != 200) {
         echo "\n"."Error loading feed from " . $url . " " . $httpcode . " " . curl_error($ch) ." \n ";
         $feedn++;
         continue;
    }
    curl_close($ch);

    $response = str_ireplace(array("media:thumbnail", '<media:group>', '</media:group>'), array("thumbnail", '', ''), $response);
    $feed = simplexml_load_string($response);
    if ($feed) {
        $feedType = strtolower($feed->getName());
        $count = 0;
        if ($feedType === 'rss') {
            foreach ($feed->channel->item as $item) {
                if ($count >= $postLimit) {
                    break;
                }
                $description = $item->description;
                $image = null;
                if (isset($item->image) && isset($item->image->url)) {
                    $image = $item->image->url;
                } else if (isset($item->thumbnail) && isset($item->thumbnail->attributes()['url'])) {
                    $image = (string)$item->thumbnail->attributes()['url'];
                } else {   
                    preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $description, $imagematch);
                    if ($imagematch && isset($imagematch['src'])) {
                        $image = $imagematch['src'];
                    } else if (isset($feed->channel->image) && isset($feed->channel->image->url)) {
                        $image = $feed->channel->image->url;
                    }
                }
                                 
                $audiourl = null;
                if (isset($item->enclosure) && isset($item->enclosure['url']) && isset($item->enclosure['type']) && strpos($item->enclosure['type'], 'audio/mpeg') !== false) {
                    $audiourl = $item->enclosure['url'];
                }

                $feeda[$feedn]['link'] = (string) $item->link;
                $feeda[$feedn]['title'] = (string) $item->title;
                if(empty($feeda[$feedn]['title'])) {
                    $feeda[$feedn]['title'] = substr(strip_tags($description), 0, 140);
                }
                $feeda[$feedn]['ch'] = (string) $feed->channel->title;
                $feeda[$feedn]['date'] = strtotime((string) $item->pubDate);
                $feeda[$feedn]['image'] = (string) $image;
                $feeda[$feedn]['audio'] = (string) $audiourl;
                $count++; 
                $feedn++;
            }
        } elseif ($feedType === 'feed') {
            foreach ($feed->entry as $entry) {
                if ($count >= $postLimit) {
                    break;
                }
                $content = $entry->content;
                $image = null;
                if (isset($entry->image) && isset($entry->image->url)) {
                    $image = $entry->image->url;
                } elseif (isset($entry->{'thumbnail'}) && isset($entry->{'thumbnail'}->attributes()['url'])) {
                    $image = (string) $entry->{'thumbnail'}->attributes()['url'];
                } else {
                    preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $imagematch);
                    $image = ($imagematch && isset($imagematch['src'])) ? $imagematch['src'] : (isset($feed->image) && isset($feed->image->url) ? $feed->image->url : null);
                }
                $audiourl = null;
                if (isset($entry->link) && isset($entry->link['rel']) && $entry->link['rel'] == 'enclosure' && isset($entry->link['href']) && isset($entry->link['type']) && strpos($entry->link['type'], 'audio/mpeg') !== false) {
                    $audiourl = $entry->link['href'];
                }

                $feeda[$feedn]['link'] = (string) $entry->link['href'];
                $feeda[$feedn]['title'] = (string) $entry->title;
                $feeda[$feedn]['ch'] = (string) $feed->title;
                $feeda[$feedn]['date'] = strtotime((string) ($entry->published ?? $entry->updated));
                $feeda[$feedn]['image'] = (string) $image;
                $feeda[$feedn]['audio'] = (string) $audiourl;

                $count++; 
                $feedn++;
            }
        }
    } else {
         echo 'Failed to parse feed from ' . $url;
         $feedn++;
    }
}

usort($feeda, fn($a, $b) => $b['date'] <=> $a['date']);

$outhtml = '';
$outoptions = '<option value="All">All Channels</option>';
$outchannels = array();
$index = 0;

foreach($feeda as $post) {
    if(!in_array($post['ch'], $outchannels)) {
        $outoptions .= '<option value="'.htmlspecialchars($post['ch']).'">'.htmlspecialchars($post['ch']).'</option>';
        $outchannels[] = $post['ch'];
    }
    
    $isaudio = !empty($post['audio']) ? 1 : 0;
    
    // --- HIGH-FIDELITY YOUTUBE CARD RENDER ---
    $outhtml .= '<div class="post" data-channel="'.htmlspecialchars($post['ch']).'" data-ts="'.$post['date'].'" data-audio="'.$isaudio.'">';
    
    // 1. Thumbnail
    $outhtml .= '  <div class="yt-thumbnail-wrapper">';
    if(!empty($post['image'])) {
        $outhtml .= '    <img class="yt-thumbnail" src="'.htmlspecialchars($post['image']).'" alt="'.htmlspecialchars($post['title']).'" loading="lazy" />';
    } else {
        $domain = parse_url($post['link'], PHP_URL_HOST);
        $outhtml .= '    <div class="yt-thumbnail-placeholder">';
        $outhtml .= '      <img class="yt-favicon-fallback" src="https://s2.googleusercontent.com/s2/favicons?domain='.urlencode($domain).'&sz=64" alt="" />';
        $outhtml .= '      <span class="yt-domain-text">'.htmlspecialchars($domain).'</span>';
        $outhtml .= '    </div>';
    }
    $badgeText = $isaudio ? 'AUDIO' : 'VIDEO';
    $outhtml .= '    <span class="yt-badge">'.$badgeText.'</span>';
    $outhtml .= '  </div>'; // yt-thumbnail-wrapper
    
    // 2. Card Metadata Details
    $outhtml .= '  <div class="yt-details">';
    
    // Dynamic Avatar determination
    $domain = parse_url($post['link'], PHP_URL_HOST);
    $avatarUrl = !empty($post['image']) ? $post['image'] : 'https://s2.googleusercontent.com/s2/favicons?domain='.urlencode($domain).'&sz=64';
    
    $outhtml .= '    <div class="yt-avatar-container">';
    $outhtml .= '      <img class="yt-channel-avatar" src="'.htmlspecialchars($avatarUrl).'" alt="'.htmlspecialchars($post['ch']).'" onerror="this.src=\'https://s2.googleusercontent.com/s2/favicons?domain=youtube.com&sz=64\'" />';
    $outhtml .= '    </div>';
    
    // Text Information
    $outhtml .= '    <div class="yt-info">';
    $outhtml .= '      <h3 class="yt-title"><a href="'.htmlspecialchars($post['link']).'" target="_blank">'.htmlspecialchars($post['title']).'</a></h3>';
    $outhtml .= '      <div class="yt-metadata">';
    $outhtml .= '        <span class="yt-channel-name">'.htmlspecialchars($post['ch']).'</span>';
    $outhtml .= '        <span class="yt-dot">&bull;</span>';
    $outhtml .= '        <span class="yt-date">'.date('M d, Y', $post['date']).'</span>';
    $outhtml .= '      </div>';
    
    // Play Audio pill for Podcasts
    if(!empty($post['audio'])) {
        $outhtml .= '      <div class="yt-audio-trigger">';
        $outhtml .= '        <button class="yt-play-pill" data-aid="'.$index.'">';
        $outhtml .= '          <span class="material-symbols-outlined">play_arrow</span> Play Podcast';
        $outhtml .= '        </button>';
        $outhtml .= '        <audio src="'.htmlspecialchars($post['audio']).'" preload="metadata" aid="'.$index.'"></audio>';
        $outhtml .= '      </div>';
        $index++;
    }
    
    $outhtml .= '    </div>'; // yt-info
    
    // Context Actions dot icon
    $outhtml .= '    <button class="yt-more-btn"><span class="material-symbols-outlined">more_vert</span></button>';
    $outhtml .= '  </div>'; // yt-details
    
    $outhtml .= '</div>'; // post
}

file_put_contents('feed.json', json_encode($feeda));
$template = file_get_contents('base.html');
$html = str_replace(array('<!-- posts here -->','<!-- options here -->'), array($outhtml, $outoptions), $template);
file_put_contents('public/index.html', $html);

?>