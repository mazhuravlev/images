var imagesApp = angular.module('imagesApp', ['ngRoute', 'appControllers']);

imagesApp.controller('NavigationCtrl', ['$scope', '$location', function ($scope, $location) {
        $scope.isCurrentPath = function (path) {
            return $location.path() == path;
        };
    }]);

imagesApp.config(['$routeProvider',
    function($routeProvider) {
        $routeProvider.
        when('/images', {
            templateUrl: 'images.html',
            controller: 'UrlListCtrl'
        }).
        when('/cache', {
            templateUrl: 'cache.html',
            controller: 'CacheListCtrl'
        }).
        otherwise({
            redirectTo: '/images'
        });
    }]);