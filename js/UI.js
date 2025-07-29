"use strict";

class UserInterface
{
    static statusTimeout = null;

    /**
     * Displays a status message to the user that will be automatically hidden after a short time.
     * @param {String} message
     */
    static ShowStatus(message)
    {
        if(UserInterface.statusTimeout) {
            clearTimeout(UserInterface.statusTimeout);
        }

        const statusEl = document.getElementById('status-bar');

        statusEl.innerHTML = message;
        statusEl.hidden = false;

        // Hide the status message after 3 seconds
        UserInterface.statusTimeout = setTimeout(() => {
            statusEl.hidden = true;
            statusEl.innerHTML = '';
        }, 3000);
    }
}
