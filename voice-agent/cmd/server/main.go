package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"voice-agent/internal/agent"
	"voice-agent/internal/config"
	"voice-agent/internal/laravel"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("load config: %v", err)
	}

	logger := log.New(os.Stdout, "[voice-agent] ", log.LstdFlags|log.LUTC)

	inboundPromptBuilder, err := agent.NewPromptBuilder(cfg.PromptFile)
	if err != nil {
		logger.Fatalf("load prompt template: %v", err)
	}

	callbackPromptBuilder, err := agent.NewPromptBuilder(cfg.CallbackPromptFile)
	if err != nil {
		logger.Fatalf("load callback discovery prompt template: %v", err)
	}

	laravelClient := laravel.NewClient(cfg)
	server := agent.NewServer(cfg, logger, map[string]*agent.PromptBuilder{
		agent.ProfileInbound:           inboundPromptBuilder,
		agent.ProfileCallbackDiscovery: callbackPromptBuilder,
	}, laravelClient)

	httpServer := &http.Server{
		Addr:              ":" + cfg.Port,
		Handler:           server.Routes(),
		ReadHeaderTimeout: 10 * time.Second,
	}

	go func() {
		logger.Printf("listening on :%s", cfg.Port)
		if err := httpServer.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Fatalf("listen: %v", err)
		}
	}()

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, syscall.SIGINT, syscall.SIGTERM)
	<-stop

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	if err := httpServer.Shutdown(ctx); err != nil {
		logger.Printf("shutdown error: %v", err)
	}
}
