<?php

$lines = array();
foreach($report->logs as $entry) {
    echo '<tr>';
    
    echo '<td>'.Utilities::formatDate($entry->created).'</td>';
    
    $lid = 'report_';
    
    if($report->target_type == 'Recipient')
        $lid .= 'recipient_';
    
    if($entry->author_type == 'Guest')
        $lid .= 'guest_';
    
    $lid .= 'event_'.$entry->event;
    
    $action = Lang::tr($lid)->r(
        array(
            'event' => $entry->event,
            'date' => $entry->created,
            'time_taken' => $entry->time_taken,
            'author' => $entry->author,
            'log' => $entry
        ),
        $entry->target
    );
    
    echo '<td>'.$action.'</td>';
    
    echo '</tr>';
}
