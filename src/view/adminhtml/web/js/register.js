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
     * Convert server creation options (base64url binary fields) into the
     * PublicKeyCredentialCreationOptions structure the browser API expects.
     *
     * @param {Object} publicKey
     * @returns {Object}
     */
    function toCreationOptions(publicKey) {
        var options = $.extend(true, {}, publicKey);

        options.challenge = base64UrlToBytes(publicKey.challenge);
        options.user = $.extend({}, publicKey.user);
        options.user.id = base64UrlToBytes(publicKey.user.id);

        if ($.isArray(publicKey.excludeCredentials)) {
            options.excludeCredentials = publicKey.excludeCredentials.map(function (descriptor) {
                return $.extend({}, descriptor, {
                    id: base64UrlToBytes(descriptor.id)
                });
            });
        }

        return options;
    }

    /**
     * Read the transports reported by the authenticator, if available.
     *
     * @param {Object} response
     * @returns {Array}
     */
    function readTransports(response) {
        if (typeof response.getTransports === 'function') {
            try {
                return response.getTransports() || [];
            } catch (e) {
                return [];
            }
        }

        return [];
    }

    return function (config, element) {
        var $root = $(element),
            $button = $root.find('#passkey-register-button'),
            $label = $root.find('#passkey-label'),
            $message = $root.find('#passkey-register-message'),
            settings = {
                optionsUrl: config.optionsUrl || $root.data('optionsUrl'),
                verifyUrl: config.verifyUrl || $root.data('verifyUrl'),
                formKey: config.formKey || $root.data('formKey')
            };

        /**
         * Render a status message.
         *
         * @param {String} text
         * @param {Boolean} success
         */
        function showMessage(text, success) {
            $message
                .removeClass('message-success message-error')
                .addClass(success ? 'message-success' : 'message-error')
                .text(text)
                .show();
        }

        /**
         * Submit the verified attestation to the server.
         *
         * @param {Object} credential
         */
        function verify(credential) {
            $.post(settings.verifyUrl, {
                'form_key': settings.formKey,
                label: $label.val(),
                clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                attestationObject: bufferToBase64Url(credential.response.attestationObject),
                transports: readTransports(credential.response)
            }).done(function (response) {
                if (response && response.success) {
                    showMessage(response.message || $t('Your passkey has been registered.'), true);
                    $label.val('');
                } else {
                    showMessage((response && response.message) || $t('The passkey could not be registered.'), false);
                }
            }).fail(function () {
                showMessage($t('The passkey could not be registered.'), false);
            }).always(function () {
                $button.prop('disabled', false);
            });
        }

        /**
         * Run the create() ceremony from server-issued options.
         *
         * @param {Object} response
         */
        function startCeremony(response) {
            if (!response || !response.success || !response.publicKey) {
                showMessage((response && response.message) || $t('Unable to start passkey registration.'), false);
                $button.prop('disabled', false);
                return;
            }

            navigator.credentials.create({
                publicKey: toCreationOptions(response.publicKey)
            }).then(verify).catch(function (error) {
                if (error && (error.name === 'InvalidStateError')) {
                    showMessage($t('This authenticator is already registered.'), false);
                } else if (!error || error.name !== 'AbortError') {
                    showMessage($t('Passkey registration was cancelled or failed.'), false);
                }
                $button.prop('disabled', false);
            });
        }

        $button.on('click', function () {
            if (!window.navigator.credentials || !window.navigator.credentials.create) {
                showMessage($t('This browser does not support passkeys.'), false);
                return;
            }

            $button.prop('disabled', true);
            $message.hide();

            $.post(settings.optionsUrl, {
                'form_key': settings.formKey
            }).done(startCeremony).fail(function () {
                showMessage($t('Unable to start passkey registration.'), false);
                $button.prop('disabled', false);
            });
        });
    };
});
