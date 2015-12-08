var appControllers = angular.module('appControllers', []);

appControllers.controller('CacheListCtrl', function ($scope, $http) {
    $scope.cache = [];
    $scope.deleteCacheRecord = function (url) {
        $http.delete('/cache/' + btoa(url)).then(function (response) {
            $scope.cache = $scope.cache.filter(function (record) {
                return url !== record.url;
            });
        }, function() {
            $scope.cache = [{
                url: 'Произошла ошибка. Пожалуйста, перезагрузите страницу.'
            }];
        });
    };
    $http.get('/cache').then(function (response) {
        if (response.data instanceof Array) {
            $scope.cache = response.data;
        }
    });
});

appControllers.controller('UrlListCtrl', function ($scope, $http) {
    $scope.urlsTextInput = '';
    $scope.results = [];
    $scope.processedUrls = [];
    $scope.processUrls = function () {
        $scope.urlsTextInput = $scope.urlsTextInput
            .split('\n')
            .map(function (line) {
                if (line && !/^https?:\/\//.test(line)) {
                    return 'http://' + line;
                }
                return line;
            })
            .join('\n');
        var urls = $scope.urlsTextInput.split('\n')
            .map(function (url) {
                return url.trim();
            })
            .filter(function (url) {
                return url.length > 0 && -1 === $scope.processedUrls.indexOf(url);
            });
        urls.forEach(function (url) {
            $http.post('/process', {url: url}).then(function (response) {
                var data = response.data;
                if ('object' === typeof data && 'status' in data) {
                    $scope.processedUrls.push(url);
                    if ('ok' === data.status) {
                        data.class = 'errors' in data ? 'warning' : '';
                    } else {
                        data.url = url;
                        data.class = 'danger';
                    }
                    $scope.results.push(data);
                } else {
                    // server error
                }
            }, function() {
                //
            });
        });
    };
});