(function (Drupal) {
  Drupal.behaviors.h5pIntegration = {
    attach(context, settings) {
      window.H5PIntegration = settings.h5p.H5PIntegration;
    }
  };
})(Drupal);

