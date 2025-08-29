"use strict";

class ImageBrowser
{
    /**
     * @param {String} pageURL
     * @param {Object} ajaxMethodInfo
     * @param {String} ajaxMethodInfo.deleteImage
     * @param {String} ajaxMethodInfo.favoriteImage
     * @param {String} ajaxMethodInfo.setUpscaledImage
     * @param {String} ajaxMethodInfo.moveImage
     * @param {String} ajaxMethodInfo.setCardSize
     * @param {String} ajaxMethodInfo.setForGallery
     * @param {String} ajaxMethodInfo.setForWebsite
     * @param {String} ajaxMethodInfo.setLabel
     * @param {String} ajaxMethodInfo.copyToOutput
     * @param {String} ajaxMethodInfo.imageID
     */
    constructor(pageURL, ajaxMethodInfo)
    {
        this.images = {};
        this.pageURL = pageURL;
        this.ajaxMethodInfo = ajaxMethodInfo;
        this.filterTimeout = null;
        this.imageSelection = {};
        this.moveStack = [];
        this.moveTarget = '';
    }

    /**
     * @param {String} imageID
     * @param {Array<String>} searchWords
     */
    RegisterImage(imageID, searchWords)
    {
        this.images[imageID] = new ImageHandler(imageID, searchWords, this.HandleImageSelected.bind(this));
    }

    /**
     *
     * @param {ImageHandler} image
     * @param {boolean} selected
     */
    HandleImageSelected(image, selected)
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
     * @param {Boolean} response.upscaledFavorite
     * @param {Boolean} response.upscaledForGallery
     */
    HandleSetUpscaledResponse(image, response)
    {
        image.RemoveFromDOM();

        const upscaledImage = this.RequireImage(response.upscaledID);

        // Update the image with the modified properties
        // in case they have changed server-side.

        if(response.upscaledFavorite) {
            upscaledImage.SetFavorite(true);
        }

        if(response.upscaledForGallery) {
            upscaledImage.SetForGallery(true);
        }
    }

    CopyToOutput(imageID)
    {
        const image = this.RequireImage(imageID);

        this.SendRequest(
            image,
            this.ajaxMethodInfo.copyToOutput,
            this.HandleCopyToOutputResponse.bind(this),
            {}
        );
    }

    HandleCopyToOutputResponse(image, response)
    {
        UserInterface.ShowStatus('Image [' + image.GetID() + '] has been copied to the output folder.');
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
     * @param {ImageHandler|null} image
     * @param {String} action
     * @param {Function} successHandler
     * @param {Object|null} params
     */
    SendRequest(image, action, successHandler, params=null)
    {
        if(params === null) {
            params = {};
        }

        if(image !== null) {
            params[this.ajaxMethodInfo.imageID] = image.GetID();
        }

        params['ajax'] = action;

        const urlParams = new URLSearchParams(params);

        // check if pageURL contains a '?', if not, add it
        if (!this.pageURL.includes('?')) {
            this.pageURL += '?';
        }

        const endpoint = this.pageURL + '&' + urlParams.toString();

        console.log('AJAX | ['+action+'] | Sending request.');
        console.log('AJAX | ['+action+'] | Endpoint ['+endpoint+']');

        if(image !== null) {
            console.log('AJAX | [' + action + '] | Image [' + image.GetID() + ']');
        }

        fetch(endpoint)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('JSON response:', data);
                if(image !== null) {
                    successHandler(image, data.payload);
                } else {
                    successHandler(data.payload);
                }
            })
            .catch(error => {
                console.error('Error:', error);
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

    LabelSelected()
    {
        let label = prompt('Enter the label for the images');

        if(label === null) {
            return;
        }

        label = label.trim();

        for(const imageID in this.imageSelection) {
            const image = this.imageSelection[imageID];
            this.doSetLabel(image, label);
            image.SetSelected(false);
        }
    }

    CopySelectedToOutput()
    {
        for(const imageID in this.imageSelection) {
            const image = this.imageSelection[imageID];
            this.CopyToOutput(image.GetID());
        }
    }

    DeleteSelected()
    {
        for(const imageID in this.imageSelection) {
            const image = this.imageSelection[imageID];
            this.HandleImageSelected(image, false);
            this.DeleteImage(imageID);
        }
    }

    SelectAll()
    {
        for(const imageID in this.images) {
            const image = this.images[imageID];
            if(!image.IsSelected()) {
                image.ToggleSelection();
            }
        }
    }

    DeselectAll()
    {
        for(const imageID in this.imageSelection) {
            const image = this.imageSelection[imageID];
            if(image.IsSelected()) {
                image.ToggleSelection();
            }
        }
    }

    /**
     * @param {String} imageID
     */
    MoveImage(imageID)
    {
        const image = this.RequireImage(imageID);

        const newFolder = this.RequestFolder();

        if(newFolder !== null) {
            this.doMove(image, newFolder);
        }
    }

    doMove(image, folderName)
    {
        console.log('Move images | Folder [' + folderName + '] | Moving image [' + image.GetID() + ']...');

        this.SendRequest(
            image,
            this.ajaxMethodInfo.moveImage,
            this.HandleMoveResponse.bind(this),
            {
                'folderName': folderName
            }
        );
    }

    /**
     * @param {ImageHandler} image
     * @param {Object} response
     */
    HandleMoveResponse(image, response)
    {
        image.HandleMoved(response);

        // Handle moving multiple images
        if(this.moveStack.length > 0) {
            this.moveNext();
        } else if(this.moveTarget !== '') {
            UserInterface.ShowStatus('All selected images have been moved to <strong>' + this.moveTarget+'</strong>.');
            this.moveTarget = '';
        }
    }

    moveNext()
    {
        console.log('Move images | Folder ['+this.moveTarget+'] | ['+this.moveStack.length+'] images to move.');

        UserInterface.ShowStatus('Moving image <strong>' + this.moveStack[0] + '</strong>...');

        this.doMove(this.GetImage(this.moveStack.shift()), this.moveTarget);
    }

    /**
     * @param {String} defaultFolder
     * @returns {String|null}
     */
    RequestFolder(defaultFolder = '')
    {
        let newFolder = prompt('Enter the new folder name:', defaultFolder);

        if(newFolder === null || newFolder.trim() === '') {
            return null;
        }

        newFolder = newFolder.trim();

        if(!/^[A-Za-z0-9_-]+$/.test(newFolder)) {
            alert('Invalid folder name. Only alphanumeric characters, underscores, and hyphens are allowed.');
            return this.RequestFolder(newFolder);
        }

        return newFolder;
    }

    /**
     * @param {String} imageID
     * @param event event
     */
    HandleImageClicked(imageID, event)
    {
        if(event.ctrlKey) {
            this.ToggleSelection(imageID);
            return false;
        }

        return true;
    }

    MoveSelected()
    {
        if(this.imageSelection.length === 0) {
            UserInterface.ShowStatus('No images selected for moving.');
            return;
        }

        let newFolder = this.RequestFolder();
        if(newFolder === null) {
            return;
        }

        // Populate the move stack with the selected images
        this.moveStack = Object.keys(this.imageSelection);
        this.moveTarget = newFolder;

        // Start the moving process
        this.moveNext();
    }

    /**
     * @param {String} size
     */
    SwitchImageSize(size)
    {
        const className = 'size-' + size;

        document.getElementById('size-selector')
            .setAttribute('data-size', size);

        document.querySelectorAll('#size-selector .btn').
            forEach(el => {
                if(el.classList.contains('size-'+size)) {
                    el.classList.add('active');
                } else {
                    el.classList.remove('active');
                }
            });

        // Add the class name to all image thumbnails
        document.querySelectorAll('#image-list .image-wrapper').
            forEach(el =>
            {
                el.classList.remove('size-s', 'size-m', 'size-l', 'size-xl');
                el.classList.add(className);
            });
    }

    ApplyImageSize()
    {
        const size = document
            .getElementById('size-selector')
            .getAttribute('data-size');

        this.SendRequest(
            null,
            this.ajaxMethodInfo.setCardSize,
            this.HandleSetCardSize.bind(this),
            {
                'cardSize': size
            }
        );
    }

    HandleSetCardSize()
    {
        UserInterface.ShowStatus('The card size has been applied successfully.');
    }

    /**
     * @param {String} imageID
     */
    ToggleForGallery(imageID)
    {
        const image = this.RequireImage(imageID);

        this.SendRequest(
            image,
            this.ajaxMethodInfo.setForGallery,
            this.HandleSetForGalleryResponse.bind(this),
            {
                'forGallery': !image.IsForGallery()
            }
        );
    }

    /**
     * @param {ImageHandler} image
     * @param {Object} response
     */
    HandleSetForGalleryResponse(image, response)
    {
        image.SetForGallery(response.forGallery);

        UserInterface.ShowStatus('The image has been ' + (response.forGallery ? 'added to' : 'removed from') + ' the gallery.');
    }

    /**
     * @param {String} imageID
     */
    SetLabel(imageID)
    {
        const image = this.RequireImage(imageID);
        if(image === null) {
            alert('Image with ID [' + imageID + '] not found.');
            return;
        }

        let label = prompt('Enter the label for image [' + imageID + ']');
        if(label === null) {
            return;
        }

        this.doSetLabel(image, label);
    }

    /**
     * @param {ImageHandler} image
     * @param {String|null} label
     */
    doSetLabel(image, label)
    {
        if(label === null) {
            label = '';
        }

        this.SendRequest(
            image,
            this.ajaxMethodInfo.setLabel,
            this.HandleSetLabelResponse.bind(this),
            {
                'label': label.trim()
            }
        );
    }

    /**
     * @param {ImageHandler} image
     * @param {Object} response
     * @param {String} response.label
     */
    HandleSetLabelResponse(image, response)
    {
        image.SetLabel(response.label);

        UserInterface.ShowStatus('The label has been set to: ' + response.label);
    }

    /**
     * @param {String} imageID
     */
    ToggleForWebsite(imageID)
    {
        const image = this.RequireImage(imageID);

        this.SendRequest(
            image,
            this.ajaxMethodInfo.setForWebsite,
            this.HandleSetForWebsiteResponse.bind(this),
            {
                'forWebsite': !image.IsForWebsite()
            }
        );
    }

    /**
     * @param {ImageHandler} image
     * @param {Object} response
     * @param {Boolean} response.forWebsite
     */
    HandleSetForWebsiteResponse(image, response)
    {
        image.SetForWebsite(response.forWebsite);

        UserInterface.ShowStatus('The image has been ' + (response.forWebsite ? 'added to' : 'removed from') + ' the website.');
    }
}
