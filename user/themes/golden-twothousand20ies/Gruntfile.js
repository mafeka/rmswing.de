module.exports = function(grunt) {
  const sass = require('node-sass');
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),

    sass: {
      options: {
          implementation: sass,
          sourceMap: true,
          includePaths: [
              './node_modules/sierra-library/lib'
          ]
      },
      dist: {
          files: {
              'css/main.css': 'lib/sass/main.scss'
          }
      }
    },

      exec: {
        php_server: {
            command: 'php -S localhost:1337 system/router.php &',
            cwd: './../../../'
        }
      },

    uglify: {
      main: {
        options: {
          sourceMap: true,
          output: {
            comments: 'some'
          }

        },
        files: {
          'js/main.js': [
            'lib/js/*.js'
          ]
        }
      }
    },

    jshint: {
      options: {
        jquery: true,
        browser: true,
        devel: true,
        force: true
      },
      scripts: ['lib/js/*.js'],
      gruntfile: 'Gruntfile.js'
    },

    watch: {
      options: {
        livereload: true
      },
      sass: {
        files: ['lib/sass/**/*.scss'],
        tasks: ['sass']
      },
      js: {
        files: ['lib/js/*'],
        tasks: ['jshint', 'uglify']
      },
      templates: {
        files: ['templates/**/*.php']
      },
      content: {
        files: ['../../pages/**/*']
      }
    },
  });


  grunt.loadNpmTasks('grunt-sass');
  grunt.loadNpmTasks('grunt-autoprefixer');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-contrib-jshint');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-exec');
  grunt.registerTask('default', ['sass', 'jshint', 'uglify']);
  grunt.registerTask('dev', ['default',  'exec:php_server', 'watch']);

};
