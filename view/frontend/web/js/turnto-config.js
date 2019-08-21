/**
 * TurnTo_SocialCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2018 TurnTo Networks, Inc.
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
define([
    'jquery',
], function ($) {
    'use strict';
    return function (config) {

        if( window.turnToConfig.hasOwnProperty('sso')
        ){
            console.log('url' + url);
            window.turnToConfig.sso.userDataFn = function(contextObj){
                $.get( window.turnToConfig.baseUrl + 'turnto/sso/getuserstatus',function(data){
                    if(data.jwt === null){
                        let context = JSON.parse(atob(contextObj));
                        console.log(context);
                        window.location.replace("/turnto/sso/redirecttologin/action/"+context.action+'/authSetting/'+context.authSetting);
                    }else{
                        window.TurnToCmd('ssoRegDone', {context: contextObj, userDataToken: data.jwt});
                    }
                })

            };
        }


    }
});
