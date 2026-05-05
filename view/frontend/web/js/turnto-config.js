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
        let ttSsoBaseUrl = moduleConfig.ssoBaseUrl || '';
        let ttLogoutUrl = moduleConfig.logoutUrl || '';

        if (!window.turnToConfig || !window.turnToConfig.hasOwnProperty('sso')) {
            return;
        }

            window.turnToConfig.sso.userDataFn = function (contextObj) {
                $.get(ttSsoBaseUrl + 'getuserstatus')
                    .done(function (data) {
                        if (data.jwt === null) {
                            //user is logged out - redirect to login with message
                            let context = JSON.parse(atob(contextObj));
                            window.sessionStorage.setItem('contextObj', contextObj)
                            window.location.replace(
                                ttSsoBaseUrl + 'redirecttologin/action/'
                                + encodeURIComponent(context.action) + '/authSetting/' + encodeURIComponent(context.authSetting)
                            );
                        } else if (window.sessionStorage.getItem('contextObj')) {
                           //if user is coming from a log in redirect and the review window is triggered manually - use old context obj
                           window.TurnToCmd('ssoRegDone', {context: window.sessionStorage.getItem('contextObj'), userDataToken: data.jwt});
                            window.sessionStorage.removeItem('contextObj')
                        } else {
                         window.TurnToCmd('ssoRegDone', {context: contextObj, userDataToken: data.jwt});
                        }
                    })
                    .fail(function () {
                         window.TurnToCmd('ssoRegDone', {context: contextObj, userDataToken: null});
                    });

            };

            //When a user is redirected to PDP after login
            if(window.sessionStorage.getItem('contextObj')){
                $.get(ttSsoBaseUrl + 'getuserstatus')
                    .done(function (data){
                        window.TurnToCmd('ssoRegDone', {context: window.sessionStorage.getItem('contextObj'), userDataToken: data.jwt});
                        window.sessionStorage.removeItem('contextObj');
                    })
                    .fail(function(){
                        window.TurnToCmd('ssoRegDone', {context: window.sessionStorage.getItem('contextObj'), userDataToken: null});
                        window.sessionStorage.removeItem('contextObj');
                    });
            }

            window.turnToConfig.sso.loggedInDataFn = function(contextObj) {
                $.get(ttSsoBaseUrl + 'loggedindata')
                    .done(function (data) {
                        window.TurnToCmd('loggedInDataFnDone', {context: contextObj, userDataToken: data.jwt});
                    })
                    .fail(function () {
                        window.TurnToCmd('loggedInDataFnDone', {context: contextObj, userDataToken: null});
                    })
            };

            window.turnToConfig.sso.logout =  function() {
                window.location.replace(ttLogoutUrl);
            };
    }
});
