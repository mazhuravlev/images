var appControllers = angular.module('appControllers', []);

appControllers.controller('CacheListCtrl', function($scope, $http) {
    $scope.cache = [];
    $scope.deleteCacheRecord = function(url) {
        $http.delete('/cache/' + btoa(url)).success(function(data){
            $scope.cache = $scope.cache.filter(function(record) {
                return url !== record.url;
            });
        })
    }
    $http.get('/cache').success(function(data) {
        if(data instanceof Array) {
            $scope.cache = data;
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
            .map(function(line) {
                if(!/^https?:\/\//.test(line)) {
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
                $http.post('/process', {url: url}).success(function(data) {
                    if('status' in data) {
                        if('ok' === data.status) {
                            $scope.processedUrls.push(url);
                            data.class = 'errors' in data ? 'warning' : '';
                        } else {
                            data.class = 'danger';
                        }
                        $scope.results.push(data);
                    } else {
                        // server error
                    }
                });
        });
    };
});