// ==========================================================
// RC DRIVE - MAIN JAVASCRIPT
// ==========================================================

"use strict";


// ==========================================================
// GLOBAL USER DATA
// ==========================================================
//
// PHP creates:
//
// window.RCDriveUser = {
//     id: ...,
//     name: ...,
//     role: ...
// };
//
// We read that value here.
//

const activeUser =
    window.RCDriveUser || null;


// ==========================================================
// PAGE LOAD
// ==========================================================

document.addEventListener(
    "DOMContentLoaded",
    function () {

        console.log(
            "RC Drive JavaScript loaded successfully."
        );


        // Setup dates
        setupDates();


        // Vehicle filter
        setupVehicleFilters();


        // Rental form
        setupRentForm();


        // Home search
        setupHomeSearch();

    }
);


// ==========================================================
// SETUP DATES
// ==========================================================

function setupDates() {

    const today =
        new Date()
            .toISOString()
            .split("T")[0];


    const startDate =
        document.getElementById(
            "rentStartDate"
        );


    const endDate =
        document.getElementById(
            "rentEndDate"
        );


    const homeStart =
        document.getElementById(
            "homePickupDate"
        );


    const homeEnd =
        document.getElementById(
            "homeReturnDate"
        );


    if (startDate) {

        startDate.min =
            today;

    }


    if (endDate) {

        endDate.min =
            today;

    }


    if (homeStart) {

        homeStart.min =
            today;

    }


    if (homeEnd) {

        homeEnd.min =
            today;

    }


    // Pickup date changed

    if (startDate) {

        startDate.addEventListener(
            "change",
            function () {

                if (endDate) {

                    endDate.min =
                        startDate.value;


                    if (
                        endDate.value &&
                        endDate.value <
                        startDate.value
                    ) {

                        endDate.value =
                            startDate.value;

                    }

                }


                calculateBookingCost();

            }
        );

    }


    // Return date changed

    if (endDate) {

        endDate.addEventListener(
            "change",
            function () {

                calculateBookingCost();

            }
        );

    }

}


// ==========================================================
// VEHICLE FILTER
// ==========================================================

function setupVehicleFilters() {

    const buttons =
        document.querySelectorAll(
            ".filter-btn"
        );


    const cards =
        document.querySelectorAll(
            ".car-card"
        );


    buttons.forEach(
        function (button) {

            button.addEventListener(
                "click",
                function () {


                    buttons.forEach(
                        function (btn) {

                            btn.classList.remove(
                                "active"
                            );

                        }
                    );


                    button.classList.add(
                        "active"
                    );


                    const filter =
                        button.getAttribute(
                            "data-filter"
                        );


                    cards.forEach(
                        function (card) {

                            if (
                                filter === "all" ||
                                card.classList.contains(
                                    filter
                                )
                            ) {

                                card.style.display =
                                    "";

                            } else {

                                card.style.display =
                                    "none";

                            }

                        }
                    );

                }
            );

        }
    );

}


// ==========================================================
// RENT VEHICLE MODAL
// ==========================================================

function openRentModal(
    carName,
    price
) {

    console.log(
        "Rent button clicked:",
        carName,
        price
    );


    // ======================================================
    // LOGIN CHECK
    // ======================================================

    if (!activeUser) {

        alert(
            "Please login or register before renting a vehicle."
        );


        const loginModalElement =
            document.getElementById(
                "loginModal"
            );


        if (loginModalElement) {

            const loginModal =
                bootstrap.Modal.getOrCreateInstance(
                    loginModalElement
                );


            loginModal.show();

        }


        return;

    }


    // ======================================================
    // GET ELEMENTS
    // ======================================================

    const modal =
        document.getElementById(
            "rentModal"
        );


    const title =
        document.getElementById(
            "modalVehicleTitle"
        );


    const rateText =
        document.getElementById(
            "modalVehicleRate"
        );


    const vehicleName =
        document.getElementById(
            "selectedCarName"
        );


    const vehicleRate =
        document.getElementById(
            "selectedCarRate"
        );


    const customerName =
        document.getElementById(
            "rentCustomerName"
        );


    if (!modal) {

        console.error(
            "rentModal was not found."
        );

        return;

    }


    // ======================================================
    // UPDATE MODAL
    // ======================================================

    if (title) {

        title.textContent =
            "Rent " + carName;

    }


    if (rateText) {

        rateText.textContent =
            "₱" +
            Number(price).toLocaleString(
                "en-PH"
            ) +
            " per day";

    }


    if (vehicleName) {

        vehicleName.value =
            carName;

    }


    if (vehicleRate) {

        vehicleRate.value =
            price;

    }


    if (
        customerName &&
        activeUser
    ) {

        customerName.value =
            activeUser.name || "";

    }


    // ======================================================
    // RESET DATES
    // ======================================================

    const today =
        new Date()
            .toISOString()
            .split("T")[0];


    const startDate =
        document.getElementById(
            "rentStartDate"
        );


    const endDate =
        document.getElementById(
            "rentEndDate"
        );


    if (startDate) {

        startDate.min =
            today;

        startDate.value =
            today;

    }


    if (endDate) {

        endDate.min =
            today;

        endDate.value =
            today;

    }


    // Calculate total

    calculateBookingCost();


    // ======================================================
    // SHOW MODAL
    // ======================================================

    const rentModal =
        bootstrap.Modal.getOrCreateInstance(
            modal
        );


    rentModal.show();

}


// ==========================================================
// RENT FORM
// ==========================================================

function setupRentForm() {

    const form =
        document.getElementById(
            "rentForm"
        );


    if (!form) {

        return;

    }


    form.addEventListener(
        "submit",
        function (event) {


            // =================================================
            // LOGIN CHECK
            // =================================================

            if (!activeUser) {

                event.preventDefault();

                alert(
                    "Please login before making a booking."
                );

                return;

            }


            // =================================================
            // VEHICLE VALIDATION
            // =================================================

            const vehicle =
                document.getElementById(
                    "selectedCarName"
                );


            if (
                !vehicle ||
                !vehicle.value
            ) {

                event.preventDefault();

                alert(
                    "Please select a vehicle."
                );

                return;

            }


            // =================================================
            // PHONE VALIDATION
            // =================================================

            const phone =
                document.getElementById(
                    "rentPhone"
                );


            if (
                phone &&
                !phone.checkValidity()
            ) {

                event.preventDefault();

                phone.reportValidity();

                return;

            }


            // =================================================
            // DATE VALIDATION
            // =================================================

            const start =
                document.getElementById(
                    "rentStartDate"
                );


            const end =
                document.getElementById(
                    "rentEndDate"
                );


            if (
                !start ||
                !end ||
                !start.value ||
                !end.value
            ) {

                event.preventDefault();

                alert(
                    "Please select both rental dates."
                );

                return;

            }


            if (
                end.value <
                start.value
            ) {

                event.preventDefault();

                alert(
                    "Return date cannot be earlier than the pickup date."
                );

                return;

            }


            // =================================================
            // CONFIRMATION
            // =================================================

            const total =
                document.getElementById(
                    "calculatedTotal"
                );


            const confirmed =
                confirm(

                    "Submit this vehicle rental request?\n\n" +

                    "Vehicle: " +
                    vehicle.value +
                    "\n" +

                    "Total: " +
                    (
                        total
                            ? total.textContent
                            : ""
                    )

                );


            if (!confirmed) {

                event.preventDefault();

                return;

            }


            /*
             * IMPORTANT:
             *
             * We DO NOT use event.preventDefault()
             * after confirmation.
             *
             * Therefore the form continues normally:
             *
             * rentForm
             *      ↓
             * rent_function.php
             *      ↓
             * MySQL
             */

            console.log(
                "Rental form submitted."
            );

        }
    );

}


// ==========================================================
// CALCULATE RENTAL DAYS
// ==========================================================

function calculateDaysBetween(
    startDateString,
    endDateString
) {

    if (
        !startDateString ||
        !endDateString
    ) {

        return 1;

    }


    const start =
        new Date(
            startDateString +
            "T00:00:00"
        );


    const end =
        new Date(
            endDateString +
            "T00:00:00"
        );


    const difference =
        end.getTime() -
        start.getTime();


    const days =
        Math.ceil(
            difference /
            (1000 * 60 * 60 * 24)
        );


    return days < 1
        ? 1
        : days;

}


// ==========================================================
// CALCULATE TOTAL PRICE
// ==========================================================

function calculateBookingCost() {

    const rateInput =
        document.getElementById(
            "selectedCarRate"
        );


    const startInput =
        document.getElementById(
            "rentStartDate"
        );


    const endInput =
        document.getElementById(
            "rentEndDate"
        );


    const daysOutput =
        document.getElementById(
            "calculatedDays"
        );


    const totalOutput =
        document.getElementById(
            "calculatedTotal"
        );


    if (!rateInput) {

        return;

    }


    const rate =
        parseFloat(
            rateInput.value
        ) || 0;


    const start =
        startInput
            ? startInput.value
            : "";


    const end =
        endInput
            ? endInput.value
            : "";


    const days =
        calculateDaysBetween(
            start,
            end
        );


    const total =
        rate * days;


    // ======================================================
    // DISPLAY DAYS
    // ======================================================

    if (daysOutput) {

        daysOutput.textContent =
            days +
            (
                days === 1
                    ? " Day"
                    : " Days"
            );

    }


    // ======================================================
    // DISPLAY TOTAL
    // ======================================================

    if (totalOutput) {

        totalOutput.textContent =
            "₱" +
            total.toLocaleString(
                "en-PH",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

    }

}


// ==========================================================
// HOME SEARCH
// ==========================================================

function setupHomeSearch() {

    const start =
        document.getElementById(
            "homePickupDate"
        );


    const end =
        document.getElementById(
            "homeReturnDate"
        );


    if (
        start &&
        end
    ) {

        start.addEventListener(
            "change",
            function () {

                end.min =
                    start.value;

            }
        );

    }

}


// ==========================================================
// HOME SEARCH BUTTON
// ==========================================================

function searchFleet() {

    const start =
        document.getElementById(
            "homePickupDate"
        );


    const end =
        document.getElementById(
            "homeReturnDate"
        );


    if (
        start &&
        end &&
        start.value &&
        end.value &&
        end.value < start.value
    ) {

        alert(
            "Return date cannot be earlier than pickup date."
        );

        return;

    }


    const fleet =
        document.getElementById(
            "fleet"
        );


    if (fleet) {

        fleet.scrollIntoView({
            behavior: "smooth"
        });

    }

}


// ==========================================================
// DEBUG
// ==========================================================

console.log(
    "RC Drive script.js is ready."
);