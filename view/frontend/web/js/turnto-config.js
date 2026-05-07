/**
 * Copyright © Emplifi, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
], function ($) {
    'use strict';

    return function (config) {
        const moduleConfig = config || {};
        const ttSsoBaseUrl = moduleConfig.ssoBaseUrl ? moduleConfig.ssoBaseUrl.replace(/\/+$/, '') + '/' : '';
        const ttLogoutUrl = moduleConfig.logoutUrl || '';

        if (!window.turnToConfig || !window.turnToConfig.hasOwnProperty('sso')) {
            return;
        }

        const normalizeSsoResponse = function (response) {
            if (!response || typeof response !== 'object' || Array.isArray(response)) {
                throw new Error('Malformed SSO response: expected a JSON object.');
            }

            if (!Object.prototype.hasOwnProperty.call(response, 'jwt')) {
                throw new Error('Malformed SSO response: missing jwt field.');
            }

            return {
                success: response.success ?? (response.jwt !== null),
                loggedIn: response.logged_in ?? (response.jwt !== null),
                jwt: response.jwt,
                error: response.error && typeof response.error === 'object' ? response.error : null
            };
        };

        const ssoGet = function (url) {
            return $.get(url).then(function (response) {
                return normalizeSsoResponse(response);
            });
        };

        const logSsoError = function (response) {
            if (response && response.error && response.error.code) {
                console.warn(
                    'TurnTo SSO response warning:',
                    response.error.code,
                    response.error.message || ''
                );
            }
        };

        const getSsoFailMessage = function (jqXhrOrError, textStatus, errorThrown) {
            if (errorThrown) {
                return errorThrown;
            }

            if (textStatus && typeof textStatus === 'string') {
                return textStatus;
            }

            if (jqXhrOrError instanceof Error) {
                return jqXhrOrError.message || 'Unknown SSO error';
            }

            if (jqXhrOrError && jqXhrOrError.responseJSON && jqXhrOrError.responseJSON.message) {
                return jqXhrOrError.responseJSON.message;
            }

            if (jqXhrOrError && jqXhrOrError.responseText) {
                return jqXhrOrError.responseText;
            }

            return 'Unknown SSO error';
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
                        return;
                    }

                    if (response.jwt && typeof response.jwt === 'string') {
                        window.TurnToCmd('ssoRegDone', {context: contextObj, userDataToken: response.jwt});
                        window.sessionStorage.removeItem('contextObj')
                    } else {
                        console.warn('TurnTo SSO request failed to return jwt');
                        window.sessionStorage.setItem('contextObj', contextObj)
                    }
                })
                .fail(function (jqXhr, textStatus, errorThrown) {
                    console.warn('TurnTo SSO request failed: getuserstatus', getSsoFailMessage(jqXhr, textStatus, errorThrown));
                    window.sessionStorage.setItem('contextObj', contextObj)
                });
        };

        //When a user is redirected to PDP after login
        if(window.sessionStorage.getItem('contextObj')){
            ssoGet(ttSsoBaseUrl + 'getuserstatus')
                .done(function (response){
                    logSsoError(response);
                    if (response.jwt && typeof response.jwt === 'string') {
                        //if user is coming from a log in redirect and the review window is triggered manually - use old context obj
                        window.TurnToCmd('ssoRegDone', {context: window.sessionStorage.getItem('contextObj'), userDataToken: response.jwt});
                        window.sessionStorage.removeItem('contextObj');
                    } else {
                        console.warn('TurnTo SSO request failed to return jwt');
                    }
                })
                .fail(function(jqXhr, textStatus, errorThrown){
                    console.warn('TurnTo SSO request failed: getuserstatus', getSsoFailMessage(jqXhr, textStatus, errorThrown));
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
                    console.warn('TurnTo SSO request failed: loggedindata', getSsoFailMessage(jqXhr, textStatus, errorThrown));
                })
        };

        window.turnToConfig.sso.logout =  function() {
            window.location.replace(ttLogoutUrl);
        };
    }
})
