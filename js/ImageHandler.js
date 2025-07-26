"use strict";

class ImageHandler
{
    constructor(imageID)
    {
        this.imageID = imageID;
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
