<?php

/*
 * FileSender www.filesender.org
 * 
 * Copyright (c) 2009-2012, AARNet, Belnet, HEAnet, SURFnet, UNINETT
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 * *	Redistributions of source code must retain the above copyright
 * 	notice, this list of conditions and the following disclaimer.
 * *	Redistributions in binary form must reproduce the above copyright
 * 	notice, this list of conditions and the following disclaimer in the
 * 	documentation and/or other materials provided with the distribution.
 * *	Neither the name of AARNet, Belnet, HEAnet, SURFnet and UNINETT nor the
 * 	names of its contributors may be used to endorse or promote products
 * 	derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
 
/* ---------------------------------
 * Vouchers Page
 * ---------------------------------
 * 
 */

global $config;

if (isset($_REQUEST['a'])) {
    // add voucher
    if ($_REQUEST['a'] == 'complete') {
        $statusMsg = lang('_VOUCHER_SENT');
        $statusClass = 'green';
    }
    
    // del
    if (isset($_REQUEST['a']) && isset($_REQUEST['id'])) {
        $myfileData = $functions->getVoucherData($_REQUEST['id']);
        
        if ($_REQUEST['a'] == 'del' ){
            // check if user is authenticated and allowed to delete this voucher
            if ($isAuth && $userdata['saml_uid_attribute'] == $myfileData['fileauthuseruid']) {
                if ($functions->deleteVoucher($myfileData['fileid'])) {
                    $statusMsg = lang('_VOUCHER_DELETED');
                    $statusClass = 'green';
                }
            } else {
                // log auth user tried to delete a voucher they do not have access to
                logEntry('Permission denied - attempt to delete voucher '.$myfileData['fileuid'], 'E_ERROR');
                // notify - not deleted - you do not have permission
                $statusMsg = lang('_PERMISSION_DENIED');
                $statusClass = 'red';
            }
        }
    }
}

foreach ($errorArray as $message) {
    if($message == 'err_emailnotsent') {
        $statusMsg = lang('_ERROR_SENDING_EMAIL');
        $statusClass = 'red';
    }
}

// get file data
$filedata = $functions->getVouchers();
$json_o=json_decode($filedata,true);

?>

<script type="text/javascript">
    var maximumDate = <?php echo (time() + ($config['default_daysvalid'] * 86400)) * 1000 ?>;
    var minimumDate = <?php echo (time() + 86400) * 1000 ?>;
    var maxEmailRecipients = <?php echo $config['max_email_recipients'] ?>;
    var datepickerDateFormat = '<?php echo lang('_DP_dateFormat'); ?>';
    var selectedVoucher = '';
    var nameLang = '<?php echo lang('_FILE_NAME'); ?>';
    var sizeLang = '<?php echo lang('_SIZE'); ?>';
    
    $(function() {
        //$("#fileto_msg").hide();
        $('#expiry_msg').hide();
        
        // stripe every second row in the tables
        $('#vouchertable tr:odd').addClass('altcolor');
        getDatePicker();
        
        $('#dialog-delete').dialog({
            autoOpen: false,
            height: 160,
            modal: true,
            buttons: {
                'cancelBTN': function() {
                    $(this).dialog('close');
                },
                'deleteBTN': function() { 
                    deletevoucher();
                    $(this).dialog('close');
                }
            }
        });
        
        $('.ui-dialog-buttonpane button:contains(cancelBTN)').attr('id', 'btn_cancel');
        $('#btn_cancel').html('<?php echo lang('_NO') ?>');
        $('.ui-dialog-buttonpane button:contains(deleteBTN)').attr('id', 'btn_delete');
        $('#btn_delete').html('<?php echo lang('_YES') ?>');
        
        autoCompleteEmails();
    });
    
    function hidemessages() {
        $('#fileto_msg').hide();
        $('#expiry_msg').hide();
        $('#maxemails_msg').hide();
    }
    
    function validateVoucherForm() {
        hidemessages();
        if(!validate_recipients()) return false;
        if(!validate_expiry()) return false;
        postVoucher();
    }
    
    function deletevoucher() {
        window.location.href = 'index.php?s=vouchers&a=del&id=' + selectedVoucher;
    }
    
    function confirmdelete(vid) {
        selectedVoucher = vid;
        $('#dialog-delete').dialog('open');
    }
    
    function postVoucher() {
        hidemessages();
        // post voucher data from form
        $('#voucherbutton').attr('onclick', '');
        
        var query = $('#form1').serializeArray(), json = {};
        for (i in query) json[query[i].name] = query[i].value; // create json from form1
        json['fileto'] = getRecipientsList();
        
        // post to fs_upload.php
        $.ajax({
            type: 'POST',
            url: 'fs_upload.php?type=insertVoucherAjax',
            data: {myJson: JSON.stringify(json)},
            success: function(data) {
                var data = parseJSON(data);
                
                if(data.errors) {
                    $.each(data.errors, function(i, result) {
                        if(result == 'err_tomissing') $('#fileto_msg').show(); // missing email data
                        if(result == 'err_expmissing') $('#expiry_msg').show(); // missing expiry date
                        if(result == 'err_exoutofrange') $('#expiry_msg').show(); // expiry date out of range
                        if(result == 'err_invalidemail') $('#fileto_msg').show(); // 1 or more emails invalid
                        if(result == 'not_authenticated') $('#_noauth').show(); // server returns not authenticated
                        if(result == 'err_token') $('#dialog-tokenerror').dialog('open'); // token missing or error
                        if(result == '') $('#_noauth').show(); // server returns not authenticated
                        if(result == 'err_emailnotsent') window.location.href = 'index.php?s=emailsenterror'; //
                    });
                    
                    // re-enable button if client needs to change form details
                    $('#voucherbutton').attr('onclick', 'validateVoucherForm()');
                    return;
                }
                
                if(data.status && data.status == 'complete') {
                    window.location.href = 'index.php?s=vouchers&a=complete';
                }
            },
            error: function(xhr, err) {
                // error function to display error message e.g.404 page not found
                ajaxerror(xhr.readyState, xhr.status, xhr.responseText);
            }
        });
    }
</script>

<form name="form1" id="form1" method="post">
  <div id="box_1" class="box">
    <div id="pageheading"><?php echo lang('_VOUCHERS') ?></div>
    <table style="border: 0; width: 100%;">
      <tr>
        <td id="invite_text"><?php echo lang('_SEND_NEW_VOUCHER') ?></td>
      </tr>
    </table>
  </div>
  
  <div id="box_2" class="box">
    <table style="border: 0; width: 100%;">
      <tr>
        <td class="mandatory" id="vouchers_to" style="width: 130px"><?php echo lang('_SEND_VOUCHER_TO') ?>:</td>
        <td>
          <div id="recipients_box" style="display: none"></div>
          <input id="fileto" name="fileto" title="<?php echo lang('_EMAIL_SEPARATOR_MSG') ?>" onfocus="$('#fileto_msg').hide();" onblur="addEmailRecipientBox($('#fileto').val());" type="text" size="45" />
          <br />
          
          <div id="fileto_msg" class="validation_msg" style="display:none"><?php echo lang('_INVALID_MISSING_EMAIL') ?></div>
          
          <div id="maxemails_msg" style="display: none" class="validation_msg"><?php echo lang('_MAXEMAILS').$config['max_email_recipients'] ?></div>
        </td>
      </tr>
      
      <tr>
        <td class="mandatory" id="voucher_from"><?php echo lang('_FROM') ?>:</td>
        <td>
        <?php if (count($useremail) > 1) { ?>
          <select name="filefrom" id="filefrom">
          <?php foreach($useremail as $email) { ?>
            <option><?php echo $email ?></option>
          <?php } ?>
          </select>
        <?php } else { ?>
          <div id="visible_filefrom"><?php echo $useremail[0] ?></div>
          <input name="filefrom" type="hidden" id="filefrom" value="<?php $useremail[0] ?>" /><br />
        <?php } ?>
        </td>
      </tr>
      
      <tr>
        <td class="" id="voucher_subject"><?php echo lang('_SUBJECT') ?>: (<?php echo lang('_OPTIONAL') ?>)</td>
        <td colspan="2"><input name="vouchersubject" type="text" id="vouchersubject" /></td>
      </tr>
      
      <tr>
        <td class="" id="voucher_message"><?php echo lang('_MESSAGE') ?>: (<?php echo lang('_OPTIONAL') ?>)</td>
        <td><textarea name="vouchermessage" cols="57" rows="4" id="vouchermessage"></textarea></td>
      </tr>
      
      <tr>
        <td class="mandatory" id="vouchers_expirydate"><?php echo lang('_EXPIRY_DATE') ?>:</td>
        <td>
          <input id="datepicker" onchange="validate_expiry()" title="<?php echo lang('_DP_dateFormat') ?>" />
          <div id="expiry_msg" class="validation_msg" style="display:none"><?php echo lang('_INVALID_EXPIRY_DATE') ?></div>
        </td>
      </tr>
      
      <tr>
        <td style="text-align: right; vertical-align: middle;">
          <input type="hidden" id="fileexpirydate" name="fileexpirydate" value="<?php echo date(lang('datedisplayformat'), strtotime('+'.$config['default_daysvalid'].' day'));?>" />
          <input type="hidden" name="s-token" id="s-token" value="<?php echo (isset($_SESSION['s-token'])) ? $_SESSION['s-token'] : '';?>" />
        </td>
      </tr>
      
      <tr>
        <td colspan="2">
          <div class="menu mainButton" id="voucherbutton" onclick="validateVoucherForm()">
            <a href="#" id="btn_sendvoucher" ><?php echo lang('_SEND_VOUCHER') ?></a>
          </div>
          
          <div id="_noauth" class="validation_msg" style="display:none"><?php echo lang('_AUTH_ERROR') ?></div>
        </td>
      </tr>
    </table>
  </div>
</form>

<div id="box_3" class="box">
  <div class="heading"><?php echo lang('_ACTIVE_VOUCHERS'); ?></div>
  <table id="myfiles" style="table-layout:fixed; width: 100%; padding: 4px; border-spacing: 0; border: 0">
    <tr class="headerrow">
      <td id="vouchers_header_from" class="tblmcw3 HardBreak" style="vertical-align: middle"><strong><?php echo lang("_FROM"); ?></strong></td>
      <td id="vouchers_header_to" class="tblmcw3 HardBreak" style="vertical-align: middle"><strong><?php echo lang("_TO"); ?></strong></td>
      <td id="vouchers_header_subject" class="HardBreak" style="width: 50px; vertical-align: middle"><strong><?php echo lang("_SUBJECT"); ?></strong></td>
      <td id="vouchers_header_message" class="HardBreak" style="width: 50px; vertical-align: middle"><strong><?php echo lang("_MESSAGE"); ?></strong></td>
      <td id="vouchers_header_created" class="tblmcw3 HardBreak" style="vertical-align: middle"><strong><?php echo lang("_CREATED"); ?></strong></td>
      <td id="vouchers_header_expiry" class="tblmcw3 HardBreak" style="vertical-align: middle"><strong><?php echo lang("_EXPIRY"); ?></strong></td>
      <td class="tblmcw1"></td>
    </tr>
    
    <?php
    $i = 0;
    foreach($json_o as $item) {
        $i += 1; // counter for file id's
        $altColor = ($i % 2 != 0)? 'altcolor' : '';
        echo '<tr><td class="dr7 HardBreak"></td><td class="dr7 '.$altColor.'"></td><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td></tr>';
        echo '<tr><td class="dr1 HardBreak '.$altColor.'" style="vertical-align: middle">'.$item['filefrom'].'</td>';
        echo '<td class="dr2 HardBreak '.$altColor.'" style="vertical-align: middle">'.$item['fileto'].'</td>';
        echo '<td class="dr2 HardBreak '.$altColor.'" style="text-align: center; vertical-align: middle">';
        
        if($item['filesubject'] != '') {
            echo '<i class="fa fa-file-text-o fa-lg" border="0" alt="" style="cursor:pointer;display:block; margin:auto" title="'.utf8ToHtml($item['filesubject'], true).'"></i>';
        }
        
        echo '</td><td class="dr2 HardBreak ' . $altColor . '" style="text-align: center; vertical-align: middle">';
        
        if($item['filemessage'] != '') {
            echo '<i class="fa fa-file-text-o fa-lg" border="0" alt="" style="cursor:pointer;display:block; margin:auto" title="'.utf8ToHtml($item['filemessage'], true).'"></i>'; 
        }
        
        echo '<td class="dr2 HardBreak '.$altColor.'" style="vertical-align: middle">'.date(lang('datedisplayformat'), strtotime($item['filecreateddate'])).'</td>';
        echo '<td class="dr2 HardBreak '.$altColor.'" style="vertical-align: middle">'.date(lang('datedisplayformat'), strtotime($item['fileexpirydate'])).'</td>';
        echo '<td class="dr8 '.$altColor.'" style="text-align: center; vertical-align: middle">
                <div style="cursor:pointer">
                  <i id="btn_deletevoucher_'.$i.'" class="fa fa-minus-circle fa-lg" alt="" title="'.lang('_DELETE').'" onclick="confirmdelete(\''.$item['filevoucheruid'].'\')" style="color:#ff0000;cursor:pointer;border:0"></i>
                </div>
              </td>
            </tr>'; //etc
    }
    echo '<tr><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td><td class="dr7"></td></tr>';
    ?>
  </table>
  
  <?php if($i == 0) echo lang('_NO_VOUCHERS') ?>
  
</div>

<?php require_once('files.php'); ?>

<div id="dialog-delete" style="display:none" title="<?php echo lang('_DELETE_VOUCHER') ?>">
    <p><?php echo lang('_CONFIRM_DELETE_VOUCHER'); ?></p>
</div>
