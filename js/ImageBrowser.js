"use strict";

class ImageBrowser
{
    /**
     * @param {String} pageURL
     * @param {Object} ajaxMethodInfo
     * @param {String} ajaxMethodInfo.deleteImage
     * @param {String} ajaxMethodInfo.favoriteImage
     * @param {String} ajaxMethodInfo.setUpscaledImage
     */
    constructor(pageURL, ajaxMethodInfo)
    {
        this.images = {};
        this.pageURL = pageURL;
        this.ajaxMethodInfo = ajaxMethodInfo;
        this.filterTimeout = null;
        this.imageSelection = {};
    }

    /**
     * @param {String} imageID
     * @param {Array<String>} searchWords
     */
    RegisterImage(imageID, searchWords)
    {
        this.images[imageID] = new ImageHandler(imageID, searchWords, this.onImageSelected.bind(this));
    }

    /**
     *
     * @param {ImageHandler} image
     * @param {boolean} selected
     */
    onImageSelected(image, selected)
    {
        if(!selected) {
            delete this.imageSelection[image.GetID()];
        } else {
            this.imageSelection[image.GetID()] = image;
        }

        const number = Object.keys(this.imageSelection).length;
        const elSelection = document.getElementById('footer-selection');
        const elEmpty = document.getElementById('footer-empty-selection');
        const elCount = document.querySelector('#footer-selection .selected-count');

        if(number === 0) {
            elEmpty.hidden = false;
            elSelection.hidden = true;
            return;
        }

        elEmpty.hidden = true;
        elSelection.hidden = false;
        elCount.innerText = number;
    }

    DeleteSelectedImages()
    {

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
     */
    SetUpscaledID(imageID)
    {
        let upscaledID = prompt('Enter the upscaled image ID for image [' + imageID + ']');
        if(upscaledID === null || upscaledID.trim() === '') {
            return;
        }

        const upscaledImage = this.GetImage(upscaledID.trim());
        if(upscaledImage === null) {
            alert('Upscaled image with ID [' + upscaledID + '] not found.');
            return;
        }

        this.SendRequest(
            this.RequireImage(imageID),
            this.ajaxMethodInfo.setUpscaledImage,
            this.HandleSetUpscaledResponse.bind(this),
            {
                'upscaledID': upscaledImage.GetID()
            }
        );
    }

    /**
     * @param {ImageHandler} image
     * @param {Object} response
     * @param {String} response.upscaledID
     */
    HandleSetUpscaledResponse(image, response)
    {
        image.RemoveFromDOM();
    }

    /**
     * @param {String} filter
     */
    FilterImages(filter)
    {
        if(this.filterTimeout !== null) {
            clearTimeout(this.filterTimeout);
            this.filterTimeout = null;
        }

        const search = filter.toLowerCase().trim();

        if(search.length < 2) {
            for(const imageID in this.images) {
                const image = this.images[imageID];
                image.GetDOMElement().hidden = false;
            }
            return;
        }

        this.filterTimeout = setTimeout(this.doFilterImages.bind(this, search), 600);
    }

    doFilterImages(search)
    {
        console.log('Filtering images with search string: ' + search);

        for(const imageID in this.images) {
            const image = this.images[imageID];
            image.GetDOMElement().hidden = !image.MatchesSearch(search);
        }
    }

    ToggleSelection(imageID)
    {
        this.GetImage(imageID).ToggleSelection();
    }

    ResetFilter()
    {
        document.querySelector('#image-search INPUT').value = '';
        this.FilterImages('');
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

    HandleDeleteResponse(image)
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

    FavoriteSelected()
    {
        for(const imageID in this.imageSelection) {
            const image = this.imageSelection[imageID];
            if(!image.IsFavorite()) {
                this.ToggleFavorite(imageID);
            }
        }
    }

    UnfavoriteSelected()
    {
        for (const imageID in this.imageSelection) {
            const image = this.imageSelection[imageID];
            if (image.IsFavorite()) {
                this.ToggleFavorite(imageID);
            }
        }
    }

    DeleteSelected()
    {
        for(const imageID in this.imageSelection) {
            const image = this.imageSelection[imageID];
            this.DeleteImage(imageID);
        }
    }
}
