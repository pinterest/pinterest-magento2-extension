define(['domReady!'], function(){
    "use strict";
        return function initScript(config)  {

            const params = new URL(location.href).searchParams;
            const error = params.get('error');

            // make sure popup still closes if there was a connection error
            if (error == "ERROR_CONNECT_BLOCKING" && window.opener != null && !window.opener.closed){
                window.opener.parent.location.assign(config.adminhtmlSetupUri + ("?error=ERROR_CONNECT_BLOCKING"));
                window.close();
            } 

            window.addEventListener('message', ({ origin, data }) => {
                if (origin === config.pinterestBaseUrl && data && data.type === 'pinterestInit') {
                    document.getElementById('pinterest-iframe').contentWindow.postMessage({
                        type: 'integrationInit',
                        payload: {
                            client_id: config.clientId,
                            redirect_uri: config.redirectUri,
                            state: config.state,
                            use_middleware: true,
                            partner_metadata: config.partnerMetadata,
                        }
                    }, config.pinterestBaseUrl);
                    if (error){
                        document.getElementById('pinterest-iframe').contentWindow.postMessage({
                            type: 'integrationErrors',
                            payload: {
                                errors: [{"integration_error_id": error}]
                            }
                        }, config.pinterestBaseUrl);
                    }
                }
            });
        }
 });