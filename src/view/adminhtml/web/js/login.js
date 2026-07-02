/**
 * Copyright © Falcon Media. All rights reserved.
 * https://www.falconmedia.nl
 */

define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    /**
     * Decode an unpadded base64url string into a Uint8Array.
     *
     * @param {String} value
     * @returns {Uint8Array}
     */
    function base64UrlToBytes(value) {
        var normalised = value.replace(/-/g, '+').replace(/_/g, '/'),
            padding = normalised.length % 4,
            binary,
            bytes,
            i;

        if (padding) {
            normalised += new Array(5 - padding).join('=');
        }

        binary = window.atob(normalised);
        bytes = new Uint8Array(binary.length);

        for (i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }

        return bytes;
    }

    /**
     * Encode an ArrayBuffer as an unpadded base64url string.
     *
     * @param {ArrayBuffer} buffer
     * @returns {String}
     */
    function bufferToBase64Url(buffer) {
        var bytes = new Uint8Array(buffer),
            binary = '',
            i;

        for (i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }

        return window.btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '');
    }

    /**
     * Convert server request options (base64url binary fields) into the
     * PublicKeyCredentialRequestOptions structure the browser API expects.
     *
     * @param {Object} publicKey
     * @returns {Object}
     */
    function toRequestOptions(publicKey) {
        var options = $.extend(true, {}, publicKey);

        options.challenge = base64UrlToBytes(publicKey.challenge);

        if ($.isArray(publicKey.allowCredentials)) {
            options.allowCredentials = publicKey.allowCredentials.map(function (descriptor) {
                return $.extend({}, descriptor, {
                    id: base64UrlToBytes(descriptor.id)
                });
            });
        }

        return options;
    }

    return function (config, element) {
        var $root = $(element),
            $button = $root.find('#passkey-login-button'),
            $message = $root.find('#passkey-login-message'),
            settings = {
                optionsUrl: config.optionsUrl || $root.data('optionsUrl'),
                verifyUrl: config.verifyUrl || $root.data('verifyUrl'),
                formKey: config.formKey || $root.data('formKey')
            };

        /**
         * Render a status message.
         *
         * @param {String} text
         */
        function showError(text) {
            $message
                .removeClass('message-success')
                .addClass('message-error')
                .text(text)
                .show();
        }

        /**
         * Submit the assertion to the server and complete the login.
         *
         * @param {Object} credential
         */
        function verify(credential) {
            $.post(settings.verifyUrl, {
                'form_key': settings.formKey,
                id: credential.id,
                clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                authenticatorData: bufferToBase64Url(credential.response.authenticatorData),
                signature: bufferToBase64Url(credential.response.signature),
                userHandle: credential.response.userHandle ?
                    bufferToBase64Url(credential.response.userHandle) : ''
            }).done(function (response) {
                if (response && response.success && response.redirectUrl) {
                    window.location.assign(response.redirectUrl);
                    return;
                }

                showError((response && response.message) || $t('Could not sign you in with a passkey.'));
                $button.prop('disabled', false);
            }).fail(function () {
                showError($t('Could not sign you in with a passkey.'));
                $button.prop('disabled', false);
            });
        }

        /**
         * Run the get() ceremony from server-issued options.
         *
         * @param {Object} response
         */
        function startCeremony(response) {
            if (!response || !response.success || !response.publicKey) {
                showError((response && response.message) || $t('Passwordless login is not available right now.'));
                $button.prop('disabled', false);
                return;
            }

            navigator.credentials.get({
                publicKey: toRequestOptions(response.publicKey)
            }).then(verify).catch(function (error) {
                if (!error || error.name !== 'AbortError') {
                    showError($t('Passkey sign-in was cancelled or failed.'));
                }
                $button.prop('disabled', false);
            });
        }

        $button.on('click', function () {
            if (!window.navigator.credentials || !window.navigator.credentials.get) {
                showError($t('This browser does not support passkeys.'));
                return;
            }

            $button.prop('disabled', true);
            $message.hide();

            $.post(settings.optionsUrl, {
                'form_key': settings.formKey
            }).done(startCeremony).fail(function () {
                showError($t('Passwordless login is not available right now.'));
                $button.prop('disabled', false);
            });
        });
    };
});
