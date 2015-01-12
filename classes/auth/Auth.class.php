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

if (!defined('FILESENDER_BASE'))        // Require environment (fatal)
    die('Missing environment');

/**
 * Authentication class
 * 
 * Handles user authentication logic.
 */
class Auth {
    /**
     * The curent user cache.
     */
    private static $user = null;
    
    /**
     * Attributes cache.
     */
    private static $attributes = null;
    
    /**
     * Admin status of the current user.
     */
    private static $isAdmin = null;
    
    /**
     * Is the user given by an authenticated remote service ?
     */
    private static $isRemoteApplication = false;
    
    /**
     * Is the user authenticated in a remote fashion ?
     */
    private static $isRemoteUser = false;
    
    /**
     * Is the user authenticated through a service provider ?
     */
    private static $isSP = false;
    
    /**
     * Is the user authenticated through a local service ?
     */
    private static $isLocal = false;
    
    /**
     * Is the user authenticated as guest ?
     */
    private static $isGuest = false;
    
    /**
     * Return current user if it exists.
     * 
     * @return User instance or false
     */
    public static function user() {
        if(is_null(self::$user)) { // Not already cached
            // Authentication logic
            
            if(AuthGuest::isAuthenticated()) { // Guest
                self::$attributes = AuthGuest::attributes();
                self::$isGuest = true;
                
            }else if(AuthSP::isAuthenticated()) { // SP
                self::$attributes = AuthSP::attributes();
                self::$isSP = true;
                
            }else if(AuthLocal::isAuthenticated()) { // SP
                self::$attributes = AuthLocal::attributes();
                self::$isLocal = true;
                
            }else if(Config::get('auth_remote_application_enabled')) { // Remote service
                if(AuthRemoteApplication::isAuthenticated()) {
                    self::$attributes = AuthRemoteApplication::attributes();
                    if(AuthRemoteApplication::isAdmin()) self::$isAdmin = true;
                    self::$isRemoteApplication = true;
                }
                
            }/*else if(Config::get('auth_remote_user_enabled')) { // Remote user
                if(AuthRemoteUser::isAuthenticated()) {
                    self::$attributes = AuthRemoteUser::attributes();
                    self::$isRemoteUser = true;
                }
            }*/
            
            if(!self::$attributes || !array_key_exists('uid', self::$attributes)) return null;

            self::$user = User::fromAttributes(self::$attributes);
            
            if(self::isSP() && Config::get('auth_sp_set_idp_as_user_organization')) {
                $organization = AuthSP::idp();
                if(self::$user->organization != $organization) {
                    self::$user->organization = $organization;
                    self::$user->save();
                }
            }
        }
        
        return self::$user;
    }
    
    /**
     * Tells if an user is connected.
     * 
     * @retrun bool
     */
    public static function isAuthenticated() {
        return (bool)self::user();
    }
    
    /**
     * Retreive attributes
     * 
     * @return array
     */
    public static function attributes() {
        return self::$attributes;
    }
    
    /**
     * Tells if the current user is an admin.
     * 
     * @retrun bool
     */
    public static function isAdmin() {
        if(is_null(self::$isAdmin)) {
            self::$isAdmin = false;
            
            if(self::user()) {
                $admin = Config::get('admin');
                if(!is_array($admin)) $admin = array_filter(array_map('trim', preg_split('`,;\s`', (string)$admin)));
                
                self::$isAdmin = in_array(self::user()->id, $admin);
            }
        }
        
        return self::$isAdmin && !self::$isGuest;
    }
    
    /**
     * Tells if the user is given by an authenticated remote application.
     * 
     * @return bool
     */
    public static function isRemoteApplication() {
        return self::$isRemoteApplication;
    }
    
    /**
     * Tells if the user authenticated in a remote fashion.
     * 
     * @return bool
     */
    public static function isRemoteUser() {
        return self::$isRemoteUser;
    }
    
    /**
     * Tells if the user authenticated in a remote fashion or is from a remote application.
     * 
     * @return bool
     */
    public static function isRemote() {
        return self::isRemoteApplication() || self::isRemoteUser();
    }
    
    /**
     * Tells if the user authenticated through a service provider.
     * 
     * @return bool
     */
    public static function isSP() {
        return self::$isSP;
    }
    
    /**
     * Tells if the user authenticated through a local service.
     * 
     * @return bool
     */
    public static function isLocal() {
        return self::$isLocal;
    }
    
    /**
     * Tells if the user authenticated as guest.
     * 
     * @return bool
     */
    public static function isGuest() {
        return self::$isGuest;
    }
}
