/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
], function ($) {
    'use strict';

    return function (config) {
        let moduleConfig = config || {};
        let ttSsoBaseUrl = moduleConfig.ssoBaseUrl ? moduleConfig.ssoBaseUrl.replace(/\/+$/, '') + '/' : '';
        let ttLogoutUrl = moduleConfig.logoutUrl || '';

        if (!window.turnToConfig || !window.turnToConfig.hasOwnProperty('sso')) {
            return;
        }

        let normalizeSsoResponse = function (response) {
            if (!response || typeof response !== 'object' || Array.isArray(response)) {
                throw new Error('Malformed SSO response: expected a JSON object.');
            }

            if (!Object.prototype.hasOwnProperty.call(response, 'jwt')) {
                throw new Error('Malformed SSO response: missing jwt field.');
            }

            return {
                success: response.success ? response.success : response.jwt !== null,
                loggedIn: response.logged_in ? response.logged_in : response.jwt !== null,
                jwt: response.jwt,
                error: response.error && typeof response.error === 'object' ? response.error : null
            };
        };

        let ssoGet = function (url) {
            return $.get(url).then(function (response) {
                return normalizeSsoResponse(response);
            });
        };

        let logSsoError = function (response) {
            if (response && response.error && response.error.code) {
                console.warn(
                    'TurnTo SSO response warning:',
                    response.error.code,
                    response.error.message || ''
                );
            }
        };

        window.turnToConfig.sso.userDataFn = function (contextObj) {
            ssoGet(ttSsoBaseUrl + 'getuserstatus')
                .done(function (response) {
                    logSsoError(response);
                    if (response.loggedIn === false) {
                        //user is logged out - redirect to login with message
                        let context = JSON.parse(atob(contextObj));
                        window.sessionStorage.setItem('contextObj', contextObj)
                        window.location.replace(
                            ttSsoBaseUrl + 'redirecttologin/action/'
                            + encodeURIComponent(context.action) + '/authSetting/' + encodeURIComponent(context.authSetting)
                        );
                    } else if (window.sessionStorage.getItem('contextObj')) {
                        //if user is coming from a log in redirect and the review window is triggered manually - use old context obj
                        window.TurnToCmd('ssoRegDone', {context: window.sessionStorage.getItem('contextObj'), userDataToken: response.jwt});
                        window.sessionStorage.removeItem('contextObj')
                    } else {
                        window.TurnToCmd('ssoRegDone', {context: contextObj, userDataToken: response.jwt});
                    }
                })
                .fail(function (jqXhr, textStatus, errorThrown) {
                     window.TurnToCmd('ssoRegDone', {context: contextObj, userDataToken: null});
                     console.warn('TurnTo SSO request failed: getuserstatus', errorThrown || textStatus);
                });
        };

        //When a user is redirected to PDP after login
        if(window.sessionStorage.getItem('contextObj')){
            ssoGet(ttSsoBaseUrl + 'getuserstatus')
                .done(function (response){
                    logSsoError(response);
                    window.TurnToCmd('ssoRegDone', {context: window.sessionStorage.getItem('contextObj'), userDataToken: response.jwt});
                    window.sessionStorage.removeItem('contextObj');
                })
                .fail(function(jqXhr, textStatus, errorThrown){
                    window.TurnToCmd('ssoRegDone', {context: window.sessionStorage.getItem('contextObj'), userDataToken: null});
                    window.sessionStorage.removeItem('contextObj');
                    console.warn('TurnTo SSO request failed: getuserstatus', errorThrown || textStatus);
                });
        }

        window.turnToConfig.sso.loggedInDataFn = function(contextObj) {
            ssoGet(ttSsoBaseUrl + 'loggedindata')
                .done(function (response) {
                    logSsoError(response);
                    window.TurnToCmd('loggedInDataFnDone', {context: contextObj, userDataToken: response.jwt});
                })
                .fail(function (jqXhr, textStatus, errorThrown) {
                    window.TurnToCmd('loggedInDataFnDone', {context: contextObj, userDataToken: null});
                    console.warn('TurnTo SSO request failed: loggedindata', errorThrown || textStatus);
                })
        };

        window.turnToConfig.sso.logout =  function() {
            window.location.replace(ttLogoutUrl);
        };
    }
})
