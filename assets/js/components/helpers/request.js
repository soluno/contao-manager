'use strict';

const jQuery        = require('jquery');
const cookie        = require('cookie');
const Promise       = require('bluebird');

var cookies;

// Enable cancelling of promises
Promise.config({cancellation: true});

var createRequest = function(url, props) {

    props = props || {};

    if ('' !== getToken()) {
        if (undefined === props.headers) {
            props.headers = {};
        }

        props.headers['Authorization'] = 'Bearer ' + getToken();
    }

    return new Promise(function(resolve, reject, onCancel) {
        var req = jQuery.ajax(url, props)
            .done(function(response, textStatus, jqXHR) {

                // @todo, we can do that check here as soon as really every
                // Tenside response includes a response.status value
                // which means we can reject the promise here already
                // and thus not every caller needs to check for response.status
                /*
                if (undefined !== response.status && 'OK' !== response.status) {
                    return reject(new Error(response));
                }*/

                // Check if response contains a token, then we reset it which
                // means you will only get logged out after 10 minutes of
                // inactivity
                var token = jqXHR.getResponseHeader('Authentication');
                if (token) {
                    setToken(token);
                }

                return resolve(response);
            })
            .fail(function(err) {
                return reject(new Error(err));
            });

        onCancel(function() {
            req.abort();
        });
    });
};

var setToken = function(token) {

    var expires = new Date();
    expires.setTime(expires.getTime() + 10 * 60 * 1000); // 10 minutes

    document.cookie = cookie.serialize(
        'cpm:token', token, {
            expires: expires,
            domain: window.location.hostname,
            secure: window.location.protocol === 'https:'
        });

    // reset cookie cache
    cookies['cpm:token'] = token;
};

var getToken = function() {

    var token = _readCookie('cpm:token');
    if (token) {
        return token;
    }

    return '';
};

function _readCookie(name,c,C,i){
    if(cookies){ return cookies[name]; }

    c = document.cookie.split('; ');
    cookies = {};

    for(i=c.length-1; i>=0; i--){
        C = c[i].split('=');
        cookies[C[0]] = C[1];
    }

    return cookies[name];
}

module.exports = {
    createRequest: createRequest,
    setToken: setToken,
    getToken: getToken
};