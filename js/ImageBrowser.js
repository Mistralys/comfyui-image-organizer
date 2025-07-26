"use strict";

class ImageBrowser
{
    /**
     * @param {String} pageURL
     * @param {Object} ajaxMethodInfo
     * @param {String} ajaxMethodInfo.deleteImage
     * @param {String} ajaxMethodInfo.favoriteImage
     */
    constructor(pageURL, ajaxMethodInfo)
    {
        this.images = {};
        this.pageURL = pageURL;
        this.ajaxMethodInfo = ajaxMethodInfo;
    }

    /**
     * @param {String} imageID
     *
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
    ToggleFavorite(imageID)
    {
        const image = this.RequireImage(imageID);

        this.SendRequest(
            image,
            this.ajaxMethodInfo.favoriteImage,
            this.HandleFavoriteResponse.bind(this),
            {
                'favorite': !image.IsFavorite()
            }
        );
    }

    /**
     * @param {ImageHandler} image
     * @param {Object} response
     */
    HandleFavoriteResponse(image, response)
    {
        image.SetFavorite(response.favorite);
    }

    /**
     * @param {String} imageID
     * @returns {ImageHandler}
     */
    GetImage(imageID)
    {
        if(typeof this.images[imageID] !== 'undefined') {
            return this.images[imageID];
        }

        return null;
    }

    RequireImage(imageID)
    {
        const image = this.GetImage(imageID);
        if(image !== null) {
            return image;
        }

        throw new Error('Image not found for imageID [' + imageID + ']');
    }

    /**
     * @param {String} imageID
     */
    DeleteImage(imageID)
    {
        this.SendRequest(
            this.RequireImage(imageID),
            this.ajaxMethodInfo.deleteImage,
            this.HandleDeleteResponse.bind(this)
        );
    }

    HandleDeleteResponse(image, response)
    {
        delete this.images[image.GetID()];

        image.RemoveFromDOM();
    }

    /**
     *
     * @param {ImageHandler} image
     * @param {String} action
     * @param {Function} successHandler
     * @param {Object|null} params
     */
    SendRequest(image, action, successHandler, params=null)
    {
        if(params === null) {
            params = {};
        }

        params['imageID'] = image.GetID();
        params['ajax'] = action;

        const urlParams = new URLSearchParams(params);

        // check if pageURL contains a '?', if not, add it
        if (!this.pageURL.includes('?')) {
            this.pageURL += '?';
        }

        const endpoint = this.pageURL + '&' + urlParams.toString();

        console.log('AJAX | ['+action+'] | Sending request.');
        console.log('AJAX | ['+action+'] | Image ['+image.GetID()+']');
        console.log('AJAX | ['+action+'] | Endpoint ['+endpoint+']');


        fetch(endpoint)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                console.log('Réponse JSON :', data);
                successHandler(image, data.payload);
            })
            .catch(error => {
                console.error('Erreur :', error);
            });
    }
}
