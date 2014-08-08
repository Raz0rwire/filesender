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
 * *    Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 * *    Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
 * *    Neither the name of AARNet, Belnet, HEAnet, SURFnet and UNINETT nor the
 *     names of its contributors may be used to endorse or promote products
 *     derived from this software without specific prior written permission.
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

// Require environment (fatal)
if(!defined('FILESENDER_BASE')) die('Missing environment');

/**
 * Log utility
 */
class Logger {
    /**
     * Log levels
     */
    const ERROR = 'error';
    const WARN  = 'warn';
    const INFO  = 'info';
    const DEBUG = 'debug';
    private static $levels = array(
        'error' => 0,
        'warn' => 1,
        'info' => 2,
        'debug' => 3
    );
    
    /**
     * Logging facilities
     */
    private static $facilities = null;
    
    /**
     * Current process
     */
    private static $process = 'misc';
    
    /**
     * Set current process
     * 
     * @param string $process process name
     */
    public static function setProcess($process) {
        if(!in_array($process, array('misc', 'gui', 'rest', 'cron', 'bounce', 'cli'))) $process = 'misc';
        self::$process = $process;
    }
    
    /**
     * Setup logging facilities
     */
    private static function setup() {
        if(!is_null(self::$facilities)) return;
        
        self::$facilities = array(array('type' => 'error_log')); // Failsafe
        
        $facilities = Config::get('log_facilities');
        if(!$facilities) $facilities = array();
        if(!is_array($facilities)) $facilities = array($facilities);
        
        foreach($facilities as $index => $facility) {
            if(!is_array($facility)) $facility = array('type' => $facility);
            
            if(!array_key_exists('type', $facility))
                throw new ConfigMissingParameterException('log_facilities['.$index.'][type]');
            
            switch($facility['type']) {
                case 'file' :
                    if(!array_key_exists('path', $facility))
                        throw new ConfigMissingParameterException('log_facilities['.$index.'][path]');
                    
                    if(array_key_exists('rotate', $facility) && !in_array($facility['rotate'], array('hourly', 'daily', 'weekly', 'monthly', 'yearly')))
                        throw new ConfigBadParameterException('log_facilities['.$index.'][rotate]');
                    break;
                
                case 'syslog' :
                    $i = false;
                    if(array_key_exists('ident', $facility)) $i = $facility['ident'];
                    
                    $o = 0;
                    if(array_key_exists('option', $facility)) $o = $facility['option'];
                    
                    $f = 0;
                    if(array_key_exists('facility', $facility)) $f = $facility['facility'];
                    
                    if($i || $o || $f)
                        if(!openlog($i, $o, $f))
                            throw new ConfigBadParameterException('log_facilities['.$index.']');
                    break;
                
                case 'error_log' :
                    break;
                
                case 'callable' :
                    if(!array_key_exists('callback', $facility))
                        throw new ConfigMissingParameterException('log_facilities['.$index.'][callback]');
                    
                    if(!is_callable($facility['callback']))
                        throw new ConfigBadParameterException('log_facilities['.$index.'][callback]');
                    break;
                
                default :
                    throw new ConfigBadParameterException('log_facilities['.$index.'][typr]');
            }
            
            self::$facilities[] = $facility;
        }
        
        if(count($facilities) && count(self::$facilities) < 2) // No other than failsafe
            throw new ConfigBadParameterException('log_facilities');
        
        if(count(self::$facilities) >= 2) array_shift(self::$facilities); // Remove failsafe
    }
    
    /**
     * Log error
     * 
     * @param string $message
     */
    public static function error($message) {
        self::log(self::ERROR, $message);
    }
    
    /**
     * Log warn
     * 
     * @param string $message
     */
    public static function warn($message) {
        self::log(self::WARN, $message);
    }
    
    /**
     * Log info
     * 
     * @param string $message
     */
    public static function info($message) {
        self::log(self::INFO, $message);
    }
    
    /**
     * Log debug
     * 
     * @param string $message
     */
    public static function debug($message) {
        self::log(self::DEBUG, $message);
    }
    
    /**
     * Log message
     * 
     * @param string $message
     */
    public static function log($level, $message) {
        if(!is_scalar($message)) {
            foreach(explode("\n", print_r($message, true)) as $line)
                self::log($level, $line);
            
            return;
        }
        
        self::setup();
        
        if(!array_key_exists($level, self::$levels))
            $level = 'error';
        
        $message = '['.self::$process.':'.$level.'] '.$message;
        
        foreach(self::$facilities as $facility) {
            if(self::dontLog($level, $facility)) continue;
            
            $method = get_called_class().'::log_'.$facility['type'];
            call_user_func($method, $facility, $level, $message);
        }
    }
    
    /**
     * Check if level is high enough
     * 
     * @param string $level
     * @param array $facility
     */
    private static function dontLog($level, $facility = null) {
        $min_level = 10;
        
        if(!is_null($l = Config::get('log_level'))) {
            if(is_numeric($l)) {
                $min_level = $l;
            }else if(array_key_exists($l, self::$levels)) {
                $min_level = self::$levels[$l];
            }
        }
        
        if($facility && is_array($facility) && array_key_exists('level', $facility)) {
            $l = $facility['level'];
            
            if(is_numeric($l)) {
                $min_level = $l;
            }else if(array_key_exists($l, self::$levels)) {
                $min_level = self::$levels[$min_level];
            }
        }
        
        return $level <= $min_level;
    }
    
    /**
     * Log message to error_log (stderr)
     * 
     * @param string $message
     */
    private static function log_error_log($facility, $level, $message) {
        error_log($message);
    }
    
    /**
     * Log message to file
     * 
     * @param string $message
     */
    private static function log_file($facility, $level, $message) {
        $file = $facility['path'];
        $ext = '';
        
        if(preg_match('`^(.*/)?([^/]+)\.([a-z0-9]+)$`i', $file, $m)) {
            $file = $m[1].$m[2];
            $ext = $m[3];
        }else if(substr($file, -1) == '/') {
            $file .= 'filesender';
            $ext = 'log';
        }
        
        if(array_key_exists('by_process', $facility)) $file .= '_'.self::$process;
        
        if(array_key_exists('rotate', $facility)) switch($facility['rotate']) {
            case 'hourly' :  $file .= '_'.date('Y-m-d').'_'.date('H').'h'; break;
            case 'daily' :   $file .= '_'.date('Y-m-d'); break;
            case 'weekly' :  $file .= '_'.date('Y').'_week_'.date('W'); break;
            case 'monthly' : $file .= '_'.date('Y-m'); break;
            case 'yearly' :  $file .= '_'.date('Y'); break;
        }
        
        if($ext) $file .= '.'.$ext;
        
        if($fh = fopen($file, 'a')) {
            fwrite($fh, trim($message)."\n");
            fclose($fh);
        }else{
            self::log_error_log('[Filesender logging error] Could not log to '.$file);
            self::log_error_log($message);
        }
    }
    
    /**
     * Log message to syslog
     * 
     * @param string $message
     */
    private static function log_syslog($facility, $level, $message) {
        $priorities = array(LOG_ERR, LOG_WARNING, LOG_INFO, LOG_DEBUG);
        syslog($priorities[self::$levels[$level]], $message);
    }
    
    /**
     * Log message to callback
     * 
     * @param string $message
     */
    private static function log_callable($facility, $level, $message) {
        $facility['callback']($message, self::$process);
    }
}
