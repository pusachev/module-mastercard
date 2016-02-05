/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'OnTap_Tns/js/action/check-enrolment',
        'mage/url',
        'Magento_Ui/js/modal/modal',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, ccFormComponent, additionalValidators, setPaymentInformationAction, checkEnrolmentAction, url, modal, fullScreenLoader) {
        'use strict';

        return ccFormComponent.extend({
            defaults: {
                template: 'OnTap_Tns/payment/tns-direct',
                active: false,
                scriptLoaded: false,
                imports: {
                    onActiveChange: 'active'
                }
            },
            placeOrderHandler: null,
            validateHandler: null,
            modalWindow: null,
            onModalOpen: null,

            initObservable: function () {
                this._super()
                    .observe('active scriptLoaded');

                return this;
            },

            is3DsEnabled: function () {
                return this.getConfig()['three_d_secure'];
            },

            getConfig: function () {
                return window.checkoutConfig.payment[this.getCode()];
            },

            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },


            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },


            context: function () {
                return this;
            },


            isShowLegend: function () {
                return true;
            },


            getCode: function () {
                return 'tns_direct';
            },


            isActive: function () {
                var active = this.getCode() === this.isChecked();

                this.active(active);

                return active;
            },


            onActiveChange: function (isActive) {
                if (isActive && !this.scriptLoaded()) {
                    this.loadScript();
                }
            },


            loadScript: function () {
                var state = this.scriptLoaded;

                $('body').trigger('processStart');
                //require([this.getUrl()], function () {
                    state(true);
                    $('body').trigger('processStop');
                //});
            },

            check3DsEnrolment: function () {

            },

            openModal: function () {
                this.modalWindow = '#threedsecure_window';

                modal({
                    type: 'slide',
                    title: $.mage.__('3D-Secure'),
                    opened: $.proxy(this.onModalOpen, this),
                    closed: $.proxy(this.onModalClose, this),
                    buttons: [],
                    clickableOverlay: false
                }, $(this.modalWindow));

                $(this.modalWindow).modal('openModal');
            },

            setModalOpenCallback: function (callback) {
                this.onModalOpen = callback;
            },

            onModalClose: function () {
                this.isPlaceOrderActionAllowed(true);
            },

            iframeFormLoaded: function () {
                fullScreenLoader.stopLoader();
            },

            placeOrder: function () {
                if (this.validateHandler() && additionalValidators.validate()) {

                    this.isPlaceOrderActionAllowed(false);

                    if (this.is3DsEnabled()) {
                        var action = setPaymentInformationAction(this.messageContainer, this.getData());

                        $.when(action).done($.proxy(function() {
                            fullScreenLoader.startLoader();

                            $.when(checkEnrolmentAction()).fail($.proxy(function() {

                                this.isPlaceOrderActionAllowed(true);
                                console.log('fail', arguments);

                            }, this)).done($.proxy(function() {

                                this.isPlaceOrderActionAllowed(false);
                                console.log('success', arguments);

                                this.openModal();

                            }, this));

                        }, this)).fail($.proxy(function(){

                            this.isPlaceOrderActionAllowed(true);

                        }, this));
                    } else {
                        this._super();
                    }
                }
            }
        });
    }
);
