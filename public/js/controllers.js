var appControllers = angular.module('appControllers', []);

appControllers.controller('CacheListCtrl', function($scope, $http) {
    $scope.cache = [];
    $http.get('/cache').success(function(data) {
        if(data instanceof Array) {
            $scope.cache = data;
        }
    });
});

appControllers.controller('UrlListCtrl', function ($scope, $http) {
    $scope.urlsTextInput = 'http://rp5.ru\nhttp://ya.ru';
    $scope.results = [];
    $scope.processedUrls = [];
    $scope.processUrls = function (urlText) {
        var urls = urlText.split('\n')
            .map(function (url) {
                return url.trim();
            })
            .filter(function (url) {
                return url.length > 0 && -1 === $scope.processedUrls.indexOf(url);
            });
        urls.forEach(function (url) {
                $scope.processedUrls.push(url);
                $http.post('/count_images', {url: url}).success(function(data) {
                    if('status' in data) {
                        if('ok' === data.status) {
                            $scope.results.push({
                                url: data.url,
                                status: 'ok',
                                imageCount: data.imageCount,
                                imageSize: data.imageSize,
                                class: 'errors' in data ? 'warning' : '',
                                title: 'errors' in data ? 'ошибки :' + data.errors.join(', ') : ''
                            });
                        } else {
                            $scope.results.push({
                                url: url,
                                status: 'err',
                                imageCount: '',
                                imageSize: '',
                                class: 'danger',
                                title: 'произошла ошибка'
                            });
                        }
                    } else {
                        // server error
                    }
                });
        });
    };
});