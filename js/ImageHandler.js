"use strict";

class ImageHandler
{
    constructor(imageID)
    {
        this.imageID = imageID;
    }

    RemoveFromDOM()
    {
        const imageElement = document.getElementById('wrapper-' + this.imageID);
        if (imageElement) {
            imageElement.remove();
        } else {
            console.error('Image element not found for ID:', this.imageID);
        }
    }
}
