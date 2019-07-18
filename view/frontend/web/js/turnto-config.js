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
       //get user jwt here



        console.log(window.turnToConfig);

        //TODO Where should this code live?
        window.turnToConfig.sso.userDataFn = function(contextObj){
            //todo change this so dynamic url
            $.get('https://turnto23.dev/turnto/sso/getuserstatus',function(data){

                if("error"  in data){
                    window.location.replace("https://turnto23.dev/customer/account/login");
                }else{
                    console.log("_____________________ JWT Bellow ____________________")
                    console.log(data.jwt )
                    console.log("_____________________ ContexObj Bellow ____________________")
                    console.log(contextObj )
                    window.TurnToCmd('ssoRegDone', {context: contextObj, userDataToken: data.jwt});

                }
            })

        };

    }
});
