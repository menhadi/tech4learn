/*
Template Name: Examframe - 
Author: ExamFrame
Version: 4.3.0
Website: https://examframe.com/
Contact: ExamFrame@gmail.com
File: Main Js File
*/

(function () {
    ("use strict");

    function initFullScreen() {
        var fullscreenBtn = document.querySelector(
            '[data-toggle="fullscreen"]'
        );
        fullscreenBtn &&
            fullscreenBtn.addEventListener("click", function (e) {
                e.preventDefault();
                document.body.classList.toggle("fullscreen-enable");
                if (
                    !document.fullscreenElement &&
                    !document.mozFullScreenElement &&
                    !document.webkitFullscreenElement
                ) {
                    if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen();
                    } else if (document.documentElement.mozRequestFullScreen) {
                        document.documentElement.mozRequestFullScreen();
                    } else if (
                        document.documentElement.webkitRequestFullscreen
                    ) {
                        document.documentElement.webkitRequestFullscreen(
                            Element.ALLOW_KEYBOARD_INPUT
                        );
                    }
                } else {
                    if (document.cancelFullScreen) {
                        document.cancelFullScreen();
                    } else if (document.mozCancelFullScreen) {
                        document.mozCancelFullScreen();
                    } else if (document.webkitCancelFullScreen) {
                        document.webkitCancelFullScreen();
                    }
                }
            });

        document.addEventListener("fullscreenchange", exitHandler);
        document.addEventListener("webkitfullscreenchange", exitHandler);
        document.addEventListener("mozfullscreenchange", exitHandler);

        function exitHandler() {
            if (
                !document.webkitIsFullScreen &&
                !document.mozFullScreen &&
                !document.msFullscreenElement
            ) {
                document.body.classList.remove("fullscreen-enable");
            }
        }
    }

    function setLayoutMode(mode, modeType, modeTypeId, html) {
        var isModeTypeId = document.getElementById(modeTypeId);
        html.setAttribute(mode, modeType);
        if (isModeTypeId) {
            document.getElementById(modeTypeId).click();
        }
    }

    function initModeSetting() {
        var html = document.getElementsByTagName("HTML")[0];
        var lightDarkBtn = document.querySelectorAll(".light-dark-mode");
        if (lightDarkBtn && lightDarkBtn.length) {
            lightDarkBtn[0].addEventListener("click", function (event) {
                html.hasAttribute("data-bs-theme") &&
                html.getAttribute("data-bs-theme") == "dark"
                    ? setLayoutMode(
                          "data-bs-theme",
                          "light",
                          "layout-mode-light",
                          html
                      )
                    : setLayoutMode(
                          "data-bs-theme",
                          "dark",
                          "layout-mode-dark",
                          html
                      );
                window.dispatchEvent(new Event("resize"));
            });
        }
    }

    function initHamburgerMenu() {
        function toggleMenu(hamburgerId, menuSelector) {
            const toggle = document.getElementById(hamburgerId);
            if (!toggle) return;

            const closeMobileMenu = function () {
                const appMenu = document.querySelector(menuSelector);
                document.body.classList.remove("vertical-sidebar-enable");
                if (appMenu) appMenu.style.marginLeft = "-100%";
                document.dispatchEvent(new CustomEvent("examelite:sidebar-toggled"));
            };

            toggle.addEventListener("click", function () {
                if (window.innerWidth >= 992) return;

                const appMenu = document.querySelector(menuSelector);
                if (!appMenu) return;

                const isOpen =
                    document.body.classList.contains("vertical-sidebar-enable") ||
                    parseFloat(window.getComputedStyle(appMenu).marginLeft) === 0;
                const nextOpen = !isOpen;

                document.body.classList.toggle("vertical-sidebar-enable", nextOpen);
                appMenu.style.marginLeft = nextOpen ? "0px" : "-100%";
                document.dispatchEvent(new CustomEvent("examelite:sidebar-toggled"));
            });

            document.addEventListener("click", function (event) {
                if (
                    window.innerWidth < 992 &&
                    document.body.classList.contains("vertical-sidebar-enable")
                ) {
                    const appMenu = document.querySelector(menuSelector);
                    if (
                        appMenu &&
                        !appMenu.contains(event.target) &&
                        !toggle.contains(event.target)
                    ) {
                        closeMobileMenu();
                    }
                }
            });

            window.addEventListener("resize", function () {
                if (window.innerWidth >= 992) {
                    const appMenu = document.querySelector(menuSelector);
                    document.body.classList.remove("vertical-sidebar-enable");
                    if (appMenu) appMenu.style.removeProperty("margin-left");
                    document.dispatchEvent(new CustomEvent("examelite:sidebar-toggled"));
                }
            });
        }

        toggleMenu("topnav-hamburger-icon", ".app-menu");
        toggleMenu("student-topnav-hamburger-icon", ".app-menu");
    }

    function init() {
        initFullScreen();
        initModeSetting();
        initHamburgerMenu();
    }
    init();
})();
