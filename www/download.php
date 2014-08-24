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
 * download using PHP from a non web accessible folder
 * ---------------------------------
 *
 */
require_once('../includes/init.php');

try {
    if(!array_key_exists('token', $_REQUEST))
        throw new DownloadMissingTokenException();
    
    $token = $_REQUEST['token'];
    if(!Utilities::isValidUID($token))
        throw new DownloadBadTokenFormatException($token);
    
    $recipient = Recipient::fromToken($token); // Throws
    $transfer = $recipient->transfer;
    
    if(!array_key_exists('files_ids', $_REQUEST))
        throw new DownloadMissingFilesIDsException();
    
    $files_ids = array_filter(array_map('trim', explode(',', $_REQUEST['files_ids'])));
    
    if(!count($files_ids))
        throw new DownloadMissingFilesIDsException();
    
    $good_files_ids = array_filter($files_ids, function($id) {
        return preg_match('/^[0-9]+$/', $id) && ((int)$id > 0);
    });
    
    if(count($files_ids) != count($good_files_ids))
        throw new DownloadBadFilesIDsException(array_diff($files_ids, $good_files_ids));
    
    if(count($files_ids) > 1) { // Archive download
        
        exit;
    }
    
    $id = (int)array_shift($files_ids);
    $file = array_filter($transfer->files, function($f) use($id) {
        return $f->id == $id;
    });
    
    //if(!count($file))
        throw new FileNotFoundException(array('transfer_id : '.$transfer->id, 'file_id : '.$id));
    
    $file = array_shift($file);
    
    $ranges = null;
    if(array_key_exists('HTTP_RANGE', $_SERVER) && $_SERVER['HTTP_RANGE']) {
        try {
            if(preg_match('/bytes\s*=\s*(.+)$/i', $_SERVER['HTTP_RANGE'], $m)) {
                $parts = array_map('trim', explode(',', $m[1]));
                foreach($parts as $part) {
                    if(preg_match('/([0-9]+)?(?:-([0-9]+))?/', $part, $m)) {
                        if(!is_numeric($m[1]) && !is_numeric($m[2]))
                            throw new DownloadInvalidRangeException($part);
                        
                        $start = is_numeric($m[1]) ? (int)$m[1] : null;
                        $end = ((count($m) > 2) && is_numeric($m[2])) ? (int)$m[2] : null;
                        
                        if(is_null($end))
                            $end = $file->size;
                        
                        if(is_null($start)) {
                            if($end > 0) {
                                $start = 0;
                            } else if($end < 0) {
                                $start = $file-size + $end;
                                $end = $file->size;
                            } else throw new DownloadInvalidRangeException($part); // end can't be O
                        }
                        
                        if($start > $end)
                            throw new DownloadInvalidRangeException($part); // start can't be after or equal to end
                        
                        $ranges[] = array('start' => $start, 'end' => $end);
                    } else throw new DownloadInvalidRangeException($part);
                }
            } else throw new DownloadInvalidRangeException($_SERVER['HTTP_RANGE']);
        } catch(DownloadInvalidRangeException $e) {
            // Send 416 response if invalid range found
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header('Content-Range: bytes */' .$file->size); // Required in 416.
            exit;
        }
    }
    
    // Remove execution time limit as this may take a while
    set_time_limit(0);
    
    $read_range = function($range = null) use($file) {
        if(connection_aborted())
            exit; // Stop pointless reading if user stopped downloading
        
        $offset = $range ? $range['start'] : 0;
        
        $chunk_size = (int)Config::get('download_chunk_size');
        if(!$chunk_size) $chunk_size = 1024 * 1024;
        
        if($offset < $file->size) { // There is data to read
            for(; $offset < $file->size; $offset += $chunk_size) {
                if(connection_aborted())
                    exit; // Stop pointless reading if user stopped downloading
                
                echo $file->readChunk($offset);
                // TODO Log download progress ? 
            }
        }
        
        return ($offset >= $file->size);
    };
    
    $recipient->reportActivity();
    // TODO Log download start
    
    $done = false;
    if($ranges) {
        header('HTTP/1.1 206 Partial Content');
        
        if(count($ranges) == 1) { // Single range
            $range = array_shift($ranges);
            
            header('Content-Type: '.$file->mime_type);
            header('Content-Length: '.($range['end'] - $range['start'] + 1));
            header('Content-Range: bytes '.$range['start'].'-'.$range['end'].'/'.$file->size);
            
            // Read range data
            $done = $read_range($range);
            
        } else { // Multiple ranges
            $length = 0;
            foreach($ranges as $range) $length += $range['end'] - $range['start'] + 1;
            
            $boundary = 'range_boundary_t'.$transfer->id.'_f'.$file->id.'_s'.$file->size.'_'.uniqid();
            header('Content-Type: multipart/byteranges; boundary='.$boundary);
            header('Content-Length: '.$length);
            
            foreach($ranges as $range) {
                echo '--'.$boundary."\n";
                echo 'Content-Type: '.$file->mime_type."\n";
                echo 'Content-Range: bytes '.$range['start'].'-'.$range['end'].'/'.$file->size."\n";
                echo "\n";
                
                // Read range data
                $done = $read_range($range);
                
                echo "\n";
            }
            
            echo '--'.$boundary.'--';
        }
    } else {
        header('Content-Type: '.$file->mime_type);
        header('Content-Disposition: attachment; filename="'.$file->name.'"');
        header('Content-Length: '.$file->size);
        header('Accept-Ranges: bytes');
        
        // Read data (no range means all file)
        $read_range();
        $done = true;
    }
    
    if($done && array_key_exists('notify_upon_completion', $_REQUEST) && (bool)$_REQUEST['notify_upon_completion']) {
        // TODO Log file download end
        // TODO Notify file download
        
    }
} catch(Exception $e) {
    $path = GUI::path().'?s=exception&message='.base64_encode(Lang::tr($e->getMessage()));
    if(method_exists($e, 'getUid')) $path .= '&logid='.$e->getUid();
    header('Location: '.$path);
}
