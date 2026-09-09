<!-- =========================
     INQUIRY MODAL
========================= -->

<div
    class="modal fade"
    id="inquiryModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content bg-dark text-white border-warning">

            <div class="modal-header border-secondary">

                <h5 class="modal-title text-warning">
                    <i class="fa-solid fa-envelope"></i>
                    Contact RC Drive
                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">

                <form
                    action="inquiry_function.php"
                    method="POST"
                >

                    <div class="mb-3">

                        <label class="form-label">
                            Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control bg-secondary text-white border-0"
                            placeholder="Enter your name"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control bg-secondary text-white border-0"
                            placeholder="name@example.com"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Message
                        </label>

                        <textarea
                            name="message"
                            class="form-control bg-secondary text-white border-0"
                            rows="5"
                            placeholder="How can we help you?"
                            required
                        ></textarea>

                    </div>


                    <button
                        type="submit"
                        class="btn btn-warning w-100 fw-bold"
                    >
                        SEND MESSAGE
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>