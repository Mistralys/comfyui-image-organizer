"use strict";

class ImageHandler
{
    /**
     *
     * @param {String} imageID
     * @param {Array<String>} searchWords
     */
    constructor(imageID, searchWords = [])
    {
        this.imageID = imageID;
        this.searchWords = searchWords;
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

    GetID()
    {
        return this.imageID;
    }

    GetDOMElement()
    {
        return this.getDOMElement('#wrapper-' + this.imageID);
    }

    GetToggleElement()
    {
        return this.getDOMElement('#wrapper-' + this.imageID + ' .toggle-favorite');
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

    SetFavorite(favorite)
    {
        console.log('Image ['+this.imageID+'] | Set favorite: ' + favorite);

        const el = this.GetDOMElement();
        const toggleEl = this.GetToggleElement();

        if(favorite) {
            el.classList.add('favorite');
            toggleEl.text = 'Unfavorite';
        } else {
            el.classList.remove('favorite');
            toggleEl.text = 'Favorite';
        }
    }

    RemoveFromDOM()
    {
        this.GetDOMElement().remove();
    }
}
