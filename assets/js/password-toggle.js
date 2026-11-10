document.addEventListener('DOMContentLoaded', function () {

    /*
     * Automatically finds every password field
     * in the current page.
     */
    const passwordInputs =
        document.querySelectorAll('input[type="password"]');


    passwordInputs.forEach(function (input) {

        /*
         * Prevent duplicate toggle creation.
         */
        if (
            input.dataset.passwordToggleReady === 'true'
        ) {
            return;
        }


        input.dataset.passwordToggleReady = 'true';


        /*
         * If the password input is already inside our
         * global wrapper, reuse it.
         */
        let wrapper =
            input.closest('.password-toggle-wrapper');


        /*
         * Otherwise create the wrapper automatically.
         */
        if (!wrapper) {

            wrapper =
                document.createElement('div');

            wrapper.className =
                'password-toggle-wrapper';


            /*
             * Insert wrapper exactly where the input
             * currently exists.
             */
            input.parentNode.insertBefore(
                wrapper,
                input
            );


            /*
             * Move input inside wrapper.
             */
            wrapper.appendChild(input);
        }


        /*
         * Check if this input already has a toggle.
         */
        if (
            wrapper.querySelector(
                '.password-visibility-toggle'
            )
        ) {
            return;
        }


        /*
         * Create Show button.
         */
        const button =
            document.createElement('button');


        button.type =
            'button';


        button.className =
            'password-visibility-toggle';


        button.textContent =
            'Show';


        button.setAttribute(
            'aria-label',
            'Show password'
        );


        button.setAttribute(
            'aria-pressed',
            'false'
        );


        /*
         * Keep toggle disabled if password input
         * itself is disabled.
         */
        button.disabled =
            input.disabled;


        /*
         * Toggle password visibility.
         */
        button.addEventListener(
            'click',
            function () {

                const currentlyHidden =
                    input.type === 'password';


                if (currentlyHidden) {

                    input.type =
                        'text';

                    input.classList.add(
                        'password-visible'
                    );


                    button.textContent =
                        'Hide';


                    button.setAttribute(
                        'aria-label',
                        'Hide password'
                    );


                    button.setAttribute(
                        'aria-pressed',
                        'true'
                    );

                } else {

                    input.type =
                        'password';

                    input.classList.remove(
                        'password-visible'
                    );


                    button.textContent =
                        'Show';


                    button.setAttribute(
                        'aria-label',
                        'Show password'
                    );


                    button.setAttribute(
                        'aria-pressed',
                        'false'
                    );
                }


                /*
                 * Return focus to the password field
                 * without changing its value.
                 */
                input.focus();

            }
        );


        wrapper.appendChild(
            button
        );

    });

});