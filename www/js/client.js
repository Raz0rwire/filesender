// JavaScript Document

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
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS'
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

/**
 * Client side language handling
 */

if(!('filesender' in window)) window.filesender = {};


/**
 * AJAX webservice client
 */
window.filesender.client = {
    // REST service base path
    base_path: null,
    
    // Send a request to the webservice
    call: function(method, resource, data, callback, options) {
        if(!this.base_path) {
            var path = window.location.href;
            path = path.split('/');
            path.pop();
            path = path.join('/');
            this.base_path = path + '/rest.php';
        }
        
        if(!options) options = {};
        
        var args = {};
        if(options.args) for(var k in options.args) args[k] = options.args[k];
        args._ = (new Date()).getTime(); // Defeat cache
        var urlargs = [];
        for(var k in args) urlargs.push(k + '=' + args[k]);
        
        if(data) {
            var raw = options && ('rawdata' in options) && options.rawdata;
            
            if(!raw) data = JSON.stringify(data);
        }else data = undefined;
        
        var errorhandler = function(error) {
            filesender.ui.error(error);
        };
        if(options.error) {
            errorhandler = options.error;
            delete options.error;
        }
        
        var headers = [];
        if(options.headers) headers = options.headers;
        
        var settings = {
            cache: false,
            contentType: 'application/json;charset=utf-8',
            context: window,
            data: data,
            processData: false,
            dataType: 'json',
            beforeSend: function(xhr) {
                for(var k in headers) xhr.setRequestHeader(k, headers[k]);
            },
            error: function(xhr, status, error) {
                var msg = xhr.responseText.replace(/^\s+/, '').replace(/\s+$/, '');
                
                try {
                    var error = JSON.parse(msg);
                    errorhandler(error);
                } catch(e) {
                    filesender.ui.rawError(msg);
                }
            },
            success: callback,
            type: method.toUpperCase(),
            url: this.base_path + resource + '?' + urlargs.join('&')
        };
        
        for(var k in options) settings[k] = options[k];
        
        jQuery.ajax(settings);
    },
    
    get: function(resource, callback, options) {
        this.call('get', resource, undefined, callback, options);
    },
    
    post: function(resource, data, callback, options) {
        this.call('post', resource, data, function(data, status, xhr) {
            callback.call(this, xhr.getResponseHeader('Location'), data);
        }, options);
    },
    
    put: function(resource, data, callback, options) {
        this.call('put', resource, data, callback, options);
    },
    
    delete: function(resource, callback, options) {
        this.call('delete', resource, undefined, callback, options);
    },
    
    /**
     * Get public info about the Filesender instance
     */
    getInfo: function(callback) {
        this.get('/info', callback);
    },
    
    /**
     * Start a transfer
     * 
     * @param string from sender email
     * @param array files array of file objects with name, size and sha1 properties
     * @param array recipients array of recipients addresses
     * @param string subject optionnal subject
     * @param string message optionnal message
     * @param string expires expiry date (yyyy-mm-dd or unix timestamp)
     * @param array options array of selected option identifiers
     * @param callable callback function to call with transfer path and transfer info once done
     */
    postTransfer: function(from, files, recipients, subject, message, expires, options, guest_token, callback, onerror) {
        var opts = {};
        if(onerror) opts.error = onerror;
        if (guest_token) opts.args={vid:guest_token};
        this.post('/transfer', {
            from: from,
            files: files,
            recipients: recipients,
            subject: subject,
            message: message,
            expires: expires,
            options: options
        }, callback, opts);
    },
    
    /**
     * Post a file chunk
     * 
     * @param object file
     * @param blob chunk
     * @param callable callback
     */
    postChunk: function(file, blob, callback, onerror) {
        var opts = {
            contentType: 'application/octet-stream',
            rawdata: true
        };
        if(filesender.config.chunk_upload_security == 'key') opts.args = {key: file.uid};
        
        opts.headers = {
            'X-Filesender-File-Size': file.size,
            'X-Filesender-Chunk-Offset': -1,
            'X-Filesender-Chunk-Size': blob.size
        };
        
        if(onerror) opts.error = onerror;
        
        this.post('/file/' + file.id + '/chunk', blob, callback, opts);
    },
    
    /**
     * Put a file chunk
     * 
     * @param object file
     * @param blob chunk
     * @param int offset
     * @param callable callback
     */
    putChunk: function(file, blob, offset, callback, onerror) {
        var opts = {
            contentType: 'application/octet-stream',
            rawdata: true
        };
        if(filesender.config.chunk_upload_security == 'key') opts.args = {key: file.uid};
        
        opts.headers = {
            'X-Filesender-File-Size': file.size,
            'X-Filesender-Chunk-Offset': offset,
            'X-Filesender-Chunk-Size': blob.size
        };
        
        if(onerror) opts.error = onerror;
        
        this.put('/file/' + file.id + '/chunk/' + offset, blob, callback, opts);
    },
    
    /**
     * Signal file completion (along with checking data)
     * 
     * @param object file
     * @param object data check data
     * @param callable callback
     */
    fileComplete: function(file, data, callback, onerror) {
        var opts = {};
        if(filesender.config.chunk_upload_security == 'key') opts.args = {key: file.uid};
        
        if(!data) data = {};
        data.complete = true;
        
        if(onerror) opts.error = onerror;
        
        this.put('/file/' + file.id, data, callback, opts);
    },
    
    /**
     * Signal transfer completion (along with checking data)
     * 
     * @param object transfer
     * @param object data check data
     * @param callable callback
     */
    transferComplete: function(transfer, data, guest_token, callback, onerror) {
        var opts = {};
        if(filesender.config.chunk_upload_security == 'key') opts.args = {key: transfer.files[0].uid};
        
        if(!data) data = {};
        data.complete = true;
        
        if(onerror) opts.error = onerror;
        if (guest_token) opts.args={vid:guest_token};
        
        this.put('/transfer/' + transfer.id, data, callback, opts);
    },
    
    /**
     * Close a transfer
     * 
     * @param object transfer
     * @param callable callback
     */
    closeTransfer: function(transfer, callback, onerror) {
        var id = (typeof transfer == 'object') ? transfer.id : transfer;
        
        var opts = {};
        if(onerror) opts.error = onerror;
        
        this.put('/transfer/' + id, {closed: true}, callback, opts);
    },
    
    /**
     * Delete a transfer (admin / owner if in early statuses)
     * 
     * @param object transfer
     * @param bool nice should we notify owner/recipients about the deletion (close then delete or just delete)
     * @param callable callback
     */
    deleteTransfer: function(transfer, callback, onerror) {
        var id = transfer;
        var opts = {args: {}};
        
        if(typeof transfer == 'object') {
            id = transfer.id;
            if(filesender.config.chunk_upload_security == 'key') opts.args.key = transfer.files[0].uid;
        }
        
        if(onerror) opts.error = onerror;
        
        this.delete('/transfer/' + id, callback, opts);
    },
    
    /**
     * Delete a file
     * 
     * @param object file
     * @param callable callback
     */
    deleteFile: function(file, callback, onerror) {
        var id = file;
        var opts = {};
        
        if(typeof file == 'object')
            id = file.id;
        
        if(onerror) opts.error = onerror;
        
        this.delete('/file/' + id, callback, opts);
    },
    
    /**
     * Get recipient data
     * 
     * @param int recipient id
     * @param callable callback
     */
    getRecipient: function(id, callback) {
        this.get('/recipient/' + id, callback);
    },
    
    /**
     * Add recipient to transfer
     * 
     * @param int transfer id
     * @param string email
     * @param callable callback
     */
    addRecipient: function(transfer_id, email, callback) {
        this.post('/transfer/' + transfer_id + '/recipient', {recipient: email}, callback);
    },
    
    /**
     * Delete a recipient
     * 
     * @param object recipient
     * @param callable callback
     */
    deleteRecipient: function(recipient, callback, onerror) {
        var id = recipient;
        var opts = {};
        
        if(typeof recipient == 'object')
            id = recipient.id;
        
        if(onerror) opts.error = onerror;
        
        this.delete('/recipient/' + id, callback, opts);
    },
    
    /**
     * Get a guest voucher
     * 
     * @param mixed guest voucher object or id
     * @param callable callback
     */
    getGuest: function(voucher, callback) {
        var id = voucher;
        
        if(typeof voucher == 'object')
            id = voucher.id;
        
        this.get('/guest/' + id, callback);
    },
    
    /**
     * Create a guest voucher
     * 
     * @param string from sender email
     * @param string subject optionnal subject
     * @param string message optionnal message
     * @param string expires expiry date (yyyy-mm-dd or unix timestamp)
     * @param array options array of selected option identifiers
     * @param callable callback function to call with guest voucher path and guest voucher info once done
     */
    postGuest: function(from, recipient, subject, message, expires, options, callback) {
        this.post('/guest', {
            from: from,
            recipient: recipient,
            subject: subject,
            message: message,
            expires: expires,
            options: options
        }, callback);
    },
    
    /**
     * Delete a guest voucher
     * 
     * @param mixed guest voucher object or id
     * @param callable callback
     */
    deleteGuest: function(voucher, callback) {
        var id = voucher;
        
        if(typeof voucher == 'object')
            id = voucher.id;
        
        this.delete('/guest/' + id, callback);
    },
    
    /**
     * Override part of the config (if allowed)
     * 
     * @param object overrides
     * @param callable callback
     */
    overrideConfig: function(overrides, callback) {
        this.put('/config', overrides, callback);
    },
    
    
    getFrequentRecipients: function(needle, callback) {
        this.get('/user/@me/frequent_recipients', callback, needle ? {args: {'filterOp[contains]': needle}} : undefined);
    },
    
    getTransferOption: function(id, option, token, callback) {
        this.get('/transfer/' + id + '/options/' + option, callback, token ? {args: {token: token}} : undefined);
    },
    
    getTransferAuditlog: function(id, callback) {
        this.get('/transfer/' + id + '/auditlog', callback);
    },
    
    getTransferAuditlogByEmail: function(id, callback) {
        this.get('/transfer/' + id + '/auditlog/mail', callback);
    },
    
    getLegacyUploadProgress: function(key, callback, error) {
        this.get('/legacyuploadprogress/' + key, callback, {error: error});
    },
};
