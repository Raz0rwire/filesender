{tr:report_pdf_disclamer}

<div class="general">
    {tr:created} : <?php echo Utilities::formatDate($report->transfer->created) ?><br />
    {tr:expires} : <?php echo Utilities::formatDate($report->transfer->expires) ?><br />
    {tr:size} : <?php echo Utilities::formatBytes($report->transfer->size) ?><br />
    {tr:with_identity} : <?php echo Utilities::sanitizeOutput($report->transfer->user_email) ?><br />
    <?php if($report->transfer->guest) { ?>
    {tr:guest} : <?php echo Utilities::sanitizeOutput($report->transfer->guest->email) ?><br />
    <?php } ?>
    {tr:options} : <?php echo implode(', ', array_map(function($o) {
        return Lang::tr($o);
    }, $report->transfer->options)) ?>
</div>

<div class="recipients">
    <h2>{tr:recipients}</h2>
    
    <?php foreach($report->transfer->recipients as $recipient) { ?>
        <div class="recipient" data-id="<?php echo $recipient->id ?>">
            <?php echo Utilities::sanitizeOutput($recipient->identity) ?> : <?php echo count($recipient->downloads) ?> {tr:downloads}
        </div>
    <?php } ?>
</div>

<div class="files">
    <h2>{tr:files}</h2>
    
    <?php foreach($report->transfer->files as $file) { ?>
        <div class="file" data-id="<?php echo $file->id ?>">
            <?php echo Utilities::sanitizeOutput($file->name) ?> (<?php echo Utilities::formatBytes($file->size) ?>) : <?php echo count($file->downloads) ?> {tr:downloads}
        </div>
    <?php } ?>
</div>

<div class="auditlog">
    <h2>{tr:auditlog}</h2>
    <table class="list">
        <tr>
            <th>{tr:date}</th>
            <th>{tr:action}</th>
        </tr>
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
            'author' => $entry->author,
            'log' => $entry
        ),
        $entry->target
    );
    
    echo '<td>'.$action.'</td>';
    
    echo '</tr>';
}

?>
    </table>
</div>
