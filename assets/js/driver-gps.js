(function () {

    'use strict';


    /* ==========================================================
       GPS CONFIGURATION
    ========================================================== */

    const updateUrl = window.DRIVER_GPS_UPDATE_URL;

    const statusElement =
        document.getElementById('gps-status');

    const coordinatesElement =
        document.getElementById('gps-coordinates');

    const updatedElement =
        document.getElementById('gps-updated');

    let watchId = null;


    /* ==========================================================
       CHECK BROWSER SUPPORT
    ========================================================== */

    if (!navigator.geolocation) {

        updateStatus(
            'GPS is not supported by this browser.',
            'error'
        );

        return;
    }


    /* ==========================================================
       START GPS TRACKING
    ========================================================== */

    function startTracking() {

        updateStatus(
            'Requesting location permission...',
            'loading'
        );


        watchId = navigator.geolocation.watchPosition(

            handlePosition,

            handleError,

            {
                enableHighAccuracy: true,
                maximumAge: 5000,
                timeout: 15000
            }

        );
    }


    /* ==========================================================
       GPS SUCCESS
    ========================================================== */

    function handlePosition(position) {

        const latitude =
            position.coords.latitude;

        const longitude =
            position.coords.longitude;


        updateStatus(
            'Location detected',
            'success'
        );


        displayCoordinates(
            latitude,
            longitude
        );


        sendLocation(
            latitude,
            longitude
        );
    }


    /* ==========================================================
       SEND LOCATION TO PHP
    ========================================================== */

    function sendLocation(
        latitude,
        longitude
    ) {

        const formData = new FormData();

        formData.append(
            'latitude',
            latitude
        );

        formData.append(
            'longitude',
            longitude
        );


        fetch(updateUrl, {

            method: 'POST',

            body: formData,

            credentials: 'same-origin'

        })

        .then(function (response) {

            return response.json();

        })

        .then(function (data) {

            if (!data.success) {

                updateStatus(
                    data.message ||
                    'Unable to save location.',
                    'error'
                );

                return;
            }


            updateStatus(
                'GPS location is active',
                'success'
            );


            if (updatedElement) {

                updatedElement.textContent =
                    'Last updated: ' +
                    formatDate(
                        data.updated_at
                    );
            }

        })

        .catch(function (error) {

            console.error(
                'GPS update error:',
                error
            );

            updateStatus(
                'Unable to connect to GPS server.',
                'error'
            );
        });
    }


    /* ==========================================================
       DISPLAY COORDINATES
    ========================================================== */

    function displayCoordinates(
        latitude,
        longitude
    ) {

        if (!coordinatesElement) {
            return;
        }


        coordinatesElement.textContent =
            latitude.toFixed(7) +
            ', ' +
            longitude.toFixed(7);
    }


    /* ==========================================================
       GPS ERROR
    ========================================================== */

    function handleError(error) {

        let message =
            'Unable to get your location.';


        switch (error.code) {

            case error.PERMISSION_DENIED:

                message =
                    'Location permission was denied.';

                break;


            case error.POSITION_UNAVAILABLE:

                message =
                    'Your location is currently unavailable.';

                break;


            case error.TIMEOUT:

                message =
                    'GPS request timed out.';

                break;
        }


        updateStatus(
            message,
            'error'
        );


        console.error(
            'GPS error:',
            error
        );
    }


    /* ==========================================================
       STATUS DISPLAY
    ========================================================== */

    function updateStatus(
        message,
        type
    ) {

        if (!statusElement) {
            return;
        }


        statusElement.textContent =
            message;


        statusElement.dataset.status =
            type;
    }


    /* ==========================================================
       DATE FORMAT
    ========================================================== */

    function formatDate(dateString) {

        if (!dateString) {
            return '-';
        }


        const date =
            new Date(
                dateString.replace(' ', 'T')
            );


        if (isNaN(date.getTime())) {
            return dateString;
        }


        return date.toLocaleString();
    }


    /* ==========================================================
       START
    ========================================================== */

    startTracking();


})();