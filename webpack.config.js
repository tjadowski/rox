var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('web/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()


    .createSharedEntry('bewelcome', [
         'jquery',
         'popper.js',
         'bootstrap',
         './web/script/common/common.js',
         './src/AppBundle/Resources/scss/bewelcome.scss',
         './node_modules/cookieconsent/src/cookieconsent.js',
         './node_modules/cookieconsent/src/styles/animation.css',
         './node_modules/cookieconsent/src/styles/base.css',
         './node_modules/cookieconsent/src/styles/layout.css',
         './node_modules/cookieconsent/src/styles/media.css',
         './node_modules/cookieconsent/src/styles/themes/classic.css',
         './node_modules/cookieconsent/src/styles/themes/edgeless.css'
    ])
    .addEntry('jquery_ui', './src/AppBundle/Resources/js/jquery_ui.js')
    .addEntry('backwards', './src/AppBundle/Resources/js/backwards.js')
    .addEntry('skrollr', './src/AppBundle/Resources/js/skrollr.js')
    .addEntry('signup', './src/AppBundle/Resources/js/signup.js')
    .addEntry('landing', './src/AppBundle/Resources/public/js/landing/landing.js')

    .addEntry('search/searchpicker', './src/AppBundle/Resources/public/js/search/searchpicker.js')
    .addEntry('search/createmap', './src/AppBundle/Resources/public/js/search/createmap.js')
    .addEntry('tempusdominus', './src/AppBundle/Resources/js/tempusdominus.js')
    .addEntry('requests', './src/AppBundle/Resources/js/requests.js')
    .addEntry('treasurer', './src/AppBundle/Resources/js/treasurer.js')
    .addEntry('leaflet', './src/AppBundle/Resources/js/leaflet.js')
    .addEntry('member/autocomplete', './src/AppBundle/Resources/js/member/autocomplete.js')
    .addEntry('admin/faqs', './src/AppBundle/Resources/js/admin/faqs.js')
//    .addEntry('jquery-typewatch', './src/AppBundle/Resources/js/jquery-typewatch.js')
    .addEntry('select2', './src/AppBundle/Resources/js/select2.js')

    .enableSassLoader()
    // allow legacy applications to use $/jQuery as a global variable, make popper visible for bootstrap
    .autoProvidejQuery()
    .autoProvideVariables({
        Popper: ['popper.js', 'default'],
    })
    .addLoader({
        test: require.resolve('jquery'),
        use: [{
            loader: 'expose-loader',
            options: 'jQuery'
        },{
            loader: 'expose-loader',
            options: '$'
        }]
    })
    .addLoader({
        test: require.resolve('select2'),
        use: "imports-loader?define=>false"
    })

    .enableSourceMaps(!Encore.isProduction())

// create hashed filenames (e.g. app.abc123.css)
//    .enableVersioning()
;

module.exports = Encore.getWebpackConfig();