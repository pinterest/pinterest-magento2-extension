define(['jquery', 'domReady!'], function($){
    "use strict";
        return function initScript(config, setup_url) {
            if (window.opener) {
                // if iframe had opened this window
                if(window.opener.parent) {
                    window.opener.parent.location.reload();
                    window.close();
                }
            }

            const disconnectMultisiteHandler = (data) => {
                if (data.success) {
                    window.location.assign(config.setupURL);
                    return;
                }
                // error states
                if (data.errorTypes.includes("deletePluginMetadata")) {
                    let error = {
                        "integration_error_id": "ERROR_DELETE_PLUGIN_METADATA",
                    };
                    document.getElementById('pinterest-iframe').contentWindow.postMessage({
                        type: 'integrationErrors',
                        payload: {
                            errors: [error],
                        }
                    }, config.pinterestBaseUrl);
                } else {
                    window.location.assign(config.adminhtmlSetupUri + ("?error=ERROR_DISCONNECT"));
                }
            };

            /**
             * Does a Get call to the Delete connection controller
             */
            const disconnectConnection = () => {
                $.getJSON(config.disconnectURL, (data) => {
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                    // error states
                    if (data.errorTypes.includes("deletePluginMetadata")) {
                        let error = {
                            "integration_error_id": "ERROR_DELETE_PLUGIN_METADATA",
                        };
                        document.getElementById('pinterest-iframe').contentWindow.postMessage({
                            type: 'integrationErrors',
                            payload: {
                                errors: [error],
                            }
                        }, config.pinterestBaseUrl);
                    } else {
                        window.location.assign(config.adminhtmlSetupUri + ("?error=ERROR_DISCONNECT"));
                    }
                });
            };
            
            window.addEventListener('message', ({ origin, data }) => {
                if (origin === config.pinterestBaseUrl && data) {
                    if (data.type === 'pinterestInit') {
                        document.getElementById('pinterest-iframe').contentWindow.postMessage({
                            type: 'integrationInit',
                            payload: {
                                access_token: config.accessToken,
                                advertiser_id: config.advertiserId, 
                                merchant_id: config.merchantId,
                                tag_id: config.tagId,
                                partner_metadata: config.partnerMetadata,
                                client_id: config.clientId,
                            }
                        }, config.pinterestBaseUrl);
                        document.getElementById('pinterest-iframe').contentWindow.postMessage({
                            type: 'integrationErrors',
                            payload: {
                                errors: config.errors,
                            }
                        }, config.pinterestBaseUrl);
                    }
                    if (data.type === 'pinterestDelete') {
                        if(config.siteId) {
                            $.getJSON(`${config.disconnectURL}?storeId=${config.siteId}`, disconnectMultisiteHandler);
                        } else {
                            disconnectConnection();
                        }
                    }
                    if (data.type === 'pinterestBack') {
                        window.location.replace(config.setupURL);
                    }
                }

            });
            document.getElementById('pinterest-iframe').src = setup_url;
        }
 });
