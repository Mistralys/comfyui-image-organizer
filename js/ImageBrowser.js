"use strict";

class ImageBrowser
{
    /**
     * @param {String} pageURL
     */
    constructor(pageURL)
    {
        this.images = {};
        this.pageURL = pageURL;
    }

    /**
     * @param {String} imageID
     */
    RegisterImage(imageID)
    {
        this.images[imageID] = new ImageHandler(imageID);
    }

    /**
     * Creates a new favorite image handler.
     * @param imageID
     * @constructor
     */
    FavoriteImage(imageID)
    {

    }

    /**
     * @param {String} imageID
     */
    DeleteImage(imageID)
    {
        if(typeof this.images[imageID] === 'undefined') {
            console.error('ImageHandler not found for imageID:', imageID);
            return;
        }

        this.SendRequest(imageID, 'deleteImage', this.HandleDeleteResponse.bind(this));
    }

    HandleDeleteResponse(imageID, response)
    {
        const handler = this.images[imageID];

        delete this.images[imageID];

        handler.RemoveFromDOM();
    }

    /**
     *
     * @param {String} imageID
     * @param {String} action
     * @param {Function} successHandler
     * @param {Object|null} params
     */
    SendRequest(imageID, action, successHandler, params=null)
    {
        if(params === null) {
            params = {};
        }

        params['imageID'] = imageID;
        params['ajax'] = action;

        const urlParams = new URLSearchParams(params);

        // check if pageURL contains a '?', if not, add it
        if (!this.pageURL.includes('?')) {
            this.pageURL += '?';
        }

        fetch(this.pageURL + '&' + urlParams.toString())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                console.log('Réponse JSON :', data);
                successHandler(imageID, data);
            })
            .catch(error => {
                console.error('Erreur :', error);
            });
    }
}
