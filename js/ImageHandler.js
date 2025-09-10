"use strict";

class ImageHandler
{
    /**
     * @param {String} imageID
     * @param {Number} testNumber
     * @param {Array<String>} searchWords
     * @param {Function} selectedCallback
     */
    constructor(imageID, testNumber, searchWords, selectedCallback)
    {
        this.imageID = imageID;
        this.testNumber = testNumber;
        this.searchWords = searchWords;
        this.selected = false;
        this.wrapperID = '#wrapper-' + this.imageID;
        this.selectedCallback = selectedCallback;
    }

    /**
     * @returns {Number}
     */
    GetTestNumber()
    {
        return this.testNumber;
    }

    /**
     * @param {String} searchString
     * @returns {boolean}
     */
    MatchesSearch(searchString)
    {
        searchString = searchString.toLowerCase().trim();

        if(searchString.length === 0) {
            return true; // No search string means all images match
        }

        for(const word of this.searchWords) {
            if(word.includes(searchString)) {
                return true; // If any search word matches, return true
            }
        }

        console.log('Image [' + this.imageID + '] does not match search string: ' + searchString);
        console.log(this.searchWords);

        return false;
    }

    ToggleSelection()
    {
        this.SetSelected(!this.selected);
    }

    /**
     * @param {Boolean} selected
     */
    SetSelected(selected)
    {
        console.log('Image [' + this.imageID + '] | Set selected: ' + selected);

        let label;

        this.selected = selected;

        if(selected) {
            this.GetDOMElement().classList.add('selected');
            label = '<i class="fas fa-toggle-on"></i> Unselect';
        } else {
            this.GetDOMElement().classList.remove('selected');
            label = '<i class="fas fa-toggle-off"></i> Select';
        }

        this.getDOMElement(this.wrapperID+' .toggle-selection').innerHTML = label;

        this.selectedCallback(this, this.selected);
    }

    GetID()
    {
        return this.imageID;
    }

    GetDOMElement()
    {
        return this.getDOMElement(this.wrapperID);
    }

    getDOMElement(selector)
    {
        const el = document.querySelector(selector);
        if(el) {
            return el;
        }

        throw new Error('ImageHandler: DOM element not found for selector [' + this.imageID+'].');
    }


    IsFavorite()
    {
        return this.GetDOMElement().classList.contains('favorite');
    }

    IsSelected()
    {
        return this.selected;
    }

    /**
     * @param {Boolean} favorite
     */
    SetFavorite(favorite)
    {
        if(favorite === this.IsFavorite()) {
            return;
        }

        console.log('Image ['+this.imageID+'] | Set favorite: ' + favorite);

        const elEnabled = this.getDOMElement(this.wrapperID + ' .toggle-favorite.toggle-enabled');
        const elDisabled = this.getDOMElement(this.wrapperID + ' .toggle-favorite.toggle-disabled');

        if(favorite) {
            this.GetDOMElement().classList.add('favorite');
            elEnabled.hidden = false;
            elDisabled.hidden = true;
        } else {
            this.GetDOMElement().classList.remove('favorite');
            elEnabled.hidden = true;
            elDisabled.hidden = false;
        }
    }

    RemoveFromDOM()
    {
        this.SetSelected(false); // Deselect the image if it is selected
        this.GetDOMElement().remove();
    }

    /**
     * Handle the event when an image is moved.
     * @param {Object} imageData
     * @param {Object} imageData.properties
     * @param {String} imageData.properties.folder
     */
    HandleMoved(imageData)
    {
        const folder = imageData.properties.folder;

        console.log('Image [' + this.imageID + '] | Handle moved event to ['+folder+'].');

        this.RemoveFromDOM();
    }

    IsForGallery()
    {
        return this.GetDOMElement().classList.contains('forGallery');
    }

    /**
     * @param {Boolean} forGallery
     */
    SetForGallery(forGallery)
    {
        if(forGallery === this.IsForGallery()) {
            return; // No change needed
        }

        console.log('Image ['+this.imageID+'] | Set forGallery: ' + forGallery);

        const elEnabled = this.getDOMElement(this.wrapperID + ' .toggle-for-gallery .toggle-enabled');
        const elDisabled = this.getDOMElement(this.wrapperID + ' .toggle-for-gallery .toggle-disabled');

        if(forGallery) {
            this.GetDOMElement().classList.add('forGallery');
            elEnabled.hidden = false;
            elDisabled.hidden = true;
        } else {
            this.GetDOMElement().classList.remove('forGallery');
            elEnabled.hidden = true;
            elDisabled.hidden = false;
        }
    }

    /**
     * @param {Boolean} enabled
     */
    SetForWebsite(enabled)
    {
        if(enabled === this.IsForWebsite()) {
            return; // No change needed
        }

        console.log('Image ['+this.imageID+'] | Set forWebsite: ' + enabled);

        const elBadge = this.getDOMElement(this.wrapperID + ' .badge-for-website');
        const elEnabled = this.getDOMElement(this.wrapperID + ' .toggle-for-website .toggle-enabled');
        const elDisabled = this.getDOMElement(this.wrapperID + ' .toggle-for-website .toggle-disabled');

        if(enabled) {
            this.GetDOMElement().classList.add('forWebsite');
            elBadge.classList.add('toggle-enabled');
            elBadge.classList.remove('toggle-disabled');
            elEnabled.hidden = false;
            elDisabled.hidden = true;
        } else {
            this.GetDOMElement().classList.remove('forWebsite');
            elBadge.classList.remove('toggle-enabled');
            elBadge.classList.add('toggle-disabled');
            elEnabled.hidden = true;
            elDisabled.hidden = false;
        }
    }

    /**
     * @returns {Boolean}
     */
    IsForWebsite()
    {
        return this.GetDOMElement().classList.contains('forWebsite');
    }

    SetLabel(label)
    {
        this.getDOMElement(this.wrapperID + ' .image-label > SPAN').innerHTML = label;

        // Hide the set label link if the label is not empty
        this.getDOMElement(this.wrapperID + ' .image-label > A').hidden = label !== '';
    }
}
